<?php

namespace App\Exports;

use App\Models\Inventory;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Auth;

class InventoryExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $branch;
    protected $filter;
    protected $search;
    protected $user;

    public function __construct($branch, $filter = null, $search = null)
    {
        $this->branch = $branch;
        $this->filter = $filter;
        $this->search = $search;
        $this->user = Auth::user();
    }

    public function collection()
    {
        $query = Inventory::with(['product', 'branch'])
            ->where('branch_id', $this->branch)
            ->where('is_archived', 0); // active only

        // Apply Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('product', function ($pq) {
                    $pq->where('generic_name', 'like', "%{$this->search}%")
                       ->orWhere('brand_name', 'like', "%{$this->search}%");
                })->orWhere('batch_number', 'like', "%{$this->search}%");
            });
        }

        // Apply Filter
        if ($this->filter) {
            match ($this->filter) {
                'in_stock' => $query->where('quantity', '>=', 100),
                'low_stock' => $query->where('quantity', '>', 0)->where('quantity', '<', 100),
                'out_of_stock' => $query->where('quantity', '<=', 0),
                'nearly_expired' => $query->where('expiry_date', '>', now())
                    ->where('expiry_date', '<', now()->addDays(30)),
                'expired' => $query->where('expiry_date', '<', now()),
                default => null,
            };
        }

        $items = $query->get()->sortBy('expiry_date');

        // Group by expiry month/year for nice formatting
        $grouped = $items->groupBy(function ($item) {
            return Carbon::parse($item->expiry_date)->format('F Y');
        });

        $final = collect();

        foreach ($grouped as $monthLabel => $records) {
            $final->push((object)[
                'is_month_header' => true,
                'month_label' => $monthLabel,
            ]);

            foreach ($records as $record) {
                $record->is_month_header = false;
                $final->push($record);
            }
        }

        return $final->isEmpty() ? collect([ (object)['empty' => true] ]) : $final;
    }

    public function headings(): array
    {
        $title = "RHU-{$this->branch} Inventory Report";
        $by = $this->user?->name ?? 'Unknown';

        $filterText = '';
        if ($this->filter || $this->search) {
            $parts = [];
            if ($this->filter) {
                $labels = [
                    'in_stock' => 'In Stock (≥100)',
                    'low_stock' => 'Low Stock (1–99)',
                    'out_of_stock' => 'Out of Stock',
                    'nearly_expired' => 'Nearly Expired (<30 days)',
                    'expired' => 'Expired',
                ];
                $parts[] = $labels[$this->filter] ?? 'Filtered';
            }
            if ($this->search) $parts[] = "Search: {$this->search}";
            $filterText = ' (' . implode(', ', $parts) . ')';
        }

        return [
            "{$title}{$filterText} • Exported By: {$by}",
            '', '', '', '', '', ''
        ];
    }

    public function map($item): array
    {
        if (!empty($item->empty)) {
            return ['No records found with the current filter.', '', '', '', '', '', ''];
        }

        if (!empty($item->is_month_header)) {
            return [$item->month_label, '', '', '', '', '', ''];
        }

        $generic_name = $item->product?->generic_name ?? '—';
        $brand_name   = $item->product?->brand_name ?? '—';

        return [
            $item->batch_number,
            $generic_name,
            $brand_name,
            $item->product?->form ?? '—',
            $item->product?->strength ?? '—',
            $item->quantity,
            Carbon::parse($item->expiry_date)->translatedFormat('M d, Y'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
        ]);

        // Column headers
        $sheet->fromArray(['Batch Number', 'Generic Name', 'Brand Name', 'Form', 'Strength', 'Quantity', 'Expiry Date'], null, 'A2');
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '7F1D1D']],
        ]);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle('A3:G10000')->getFont()->setSize(12);

        // Highlight month rows
        $highest = $sheet->getHighestRow();
        for ($row = 3; $row <= $highest; $row++) {
            $val = $sheet->getCell("A{$row}")->getValue();
            if (preg_match('/^[A-Za-z]+\s+\d{4}$/', $val)) {
                $sheet->mergeCells("A{$row}:G{$row}");
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ]);
            }
        }

        return [];
    }
}
