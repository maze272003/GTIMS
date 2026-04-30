<?php

namespace App\Exports;

use App\Models\Branch;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InventoryExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithDrawings, 
    WithCustomStartCell, 
    WithEvents
{
    protected ?Builder $query = null;
    protected ?int $branchId = null;
    protected ?string $filter;
    protected ?string $search;
    protected mixed $user;
    protected string $branchName;

    public function __construct(Builder|int|string $queryOrBranch, ?string $branchNameOrFilter = null, ?string $filter = null, ?string $search = null)
    {
        $this->user = Auth::user();

        if ($queryOrBranch instanceof Builder) {
            $this->query = clone $queryOrBranch;
            $this->branchName = $branchNameOrFilter ?? 'Inventory';
            $this->filter = $filter;
            $this->search = $search;

            return;
        }

        $this->branchId = (int) $queryOrBranch;
        $this->filter = $branchNameOrFilter;
        $this->search = $filter;
        $this->branchName = Branch::query()->find($this->branchId)?->name ?? ('Branch #'.$this->branchId);
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Letterhead');
        $drawing->setDescription('Official Header');
        
        $path = public_path('/images/letterhead.png');
        if (file_exists($path)) {
            $drawing->setPath($path);
        } else {
            return [];
        }

        $drawing->setWidth(720);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetY(5);
        $drawing->setOffsetX(5);

        return $drawing;
    }

    public function startCell(): string
    {
        return 'A10';
    }

    public function collection()
    {
        $items = $this->query
            ? (clone $this->query)->get()
            : $this->buildLegacyQuery()->get()->sortBy('expiry_date');

        $final = collect();
        $grouped = $items->groupBy(fn($item) => Carbon::parse($item->expiry_date)->format('F Y'));

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

        return $final->isEmpty() ? collect([(object)['empty' => true]]) : $final;
    }

    private function buildLegacyQuery(): Builder
    {
        $query = Inventory::with(['product', 'branch'])
            ->where('branch_id', $this->branchId)
            ->where('is_archived', 0);

        if ($this->search) {
            $query->where(function ($nestedQuery) {
                $nestedQuery->whereHas('product', function ($productQuery) {
                    $productQuery->where('generic_name', 'like', "%{$this->search}%")
                        ->orWhere('brand_name', 'like', "%{$this->search}%");
                })->orWhere('batch_number', 'like', "%{$this->search}%");
            });
        }

        if ($this->filter) {
            match ($this->filter) {
                'in_stock' => $query->where('quantity', '>=', 100),
                'low_stock' => $query->where('quantity', '>', 0)->where('quantity', '<', 100),
                'out_of_stock' => $query->where('quantity', '<=', 0),
                'nearly_expired' => $query->whereBetween('expiry_date', [now(), now()->addDays(30)]),
                'expired' => $query->where('expiry_date', '<', now()),
                default => null,
            };
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Batch Number', 
            'Generic Name', 
            'Brand Name', 
            'Form', 
            'Strength', 
            'Quantity', 
            'Expiry Date'
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

        return [
            $item->batch_number,
            $item->product?->generic_name ?? '—',
            $item->product?->brand_name ?? '—',
            $item->product?->form ?? '—',
            $item->product?->strength ?? '—',
            $item->quantity,
            Carbon::parse($item->expiry_date)->translatedFormat('M d, Y'),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow(); // Last data row (including month headers + possible "no records")
                $footerRow = $highestRow + 3;           // Space before footer

                // Report Title (Row 7)
                $sheet->mergeCells('A7:G7');
                $sheet->setCellValue('A7', "{$this->branchName} Inventory Report");
                $sheet->getStyle('A7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                // Filter info (Row 8 & 9 – centered)
                $filterText = 'All Items';
                if ($this->filter) {
                    $labels = [
                        'in_stock' => 'In Stock',
                        'low_stock' => 'Low Stock',
                        'out_of_stock' => 'Out of Stock',
                        'nearly_expired' => 'Nearly Expired',
                        'expired' => 'Expired',
                    ];
                    $filterText = $labels[$this->filter] ?? ucfirst($this->filter);
                }
                if ($this->search) {
                    $filterText .= " | Search: \"{$this->search}\"";
                }

                $sheet->mergeCells('A8:G8');
                $sheet->setCellValue('A8', "Filter: {$filterText}");
                $sheet->getStyle('A8')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '4B5563']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Exported By + Timestamp at the very bottom (together)
                $by = $this->user?->name ?? 'Unknown';

                $sheet->mergeCells("A{$footerRow}:B{$footerRow}");
                $sheet->setCellValue("A{$footerRow}", "Exported By: {$by}");
                $sheet->mergeCells("C{$footerRow}:E{$footerRow}");
                $sheet->setCellValue("C{$footerRow}", "Generated on " . now()->format('F d, Y \a\\t h:i:s A'));
                $sheet->getStyle("A{$footerRow}:E{$footerRow}")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        // Header Row (A10:G10)
        $sheet->getStyle('A10:G10')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '7F1D1D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Style data rows and month headers
        for ($row = 11; $row <= $highestRow; $row++) {
            $val = $sheet->getCell("A{$row}")->getValue();

            if (preg_match('/^[A-Za-z]+\s+\d{4}$/', $val) && $sheet->getCell("B{$row}")->getValue() == '') {
                // Month Header
                $sheet->mergeCells("A{$row}:G{$row}");
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E5E7EB']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
                    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
            } else {
                // Normal rows
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]]
                ]);
                
                $sheet->getStyle("F{$row}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        return [];
    }
}
