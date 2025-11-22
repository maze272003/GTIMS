<?php

namespace App\Exports;

use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Auth;

class InventoryExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $branch;
    protected $month;
    protected $year;
    protected $user;

    public function __construct($branch = null, $month = null, $year = null)
    {
        $this->branch = $branch;
        $this->month = $month ?: now()->month;
        $this->year = $year ?: now()->year;
        $this->user = Auth::user(); // get the logged-in user
    }

    public function collection()
    {
        $query = Inventory::with(['product', 'branch'])
            ->where('is_archived', 2); // only non-archived

        if ($this->branch) {
            $query->where('branch_id', $this->branch);
        }

        return $query->get()->sortBy('expiry_date');
    }

    public function headings(): array
    {
        // First row: authorized personnel
        return [
            'Inventory Report Exported By: ' . ($this->user?->name ?? 'Unknown'),
            '', '', '', '', '', ''
        ];
    }

    public function map($item): array
    {
        return [
            $item->product?->name ?? 'Unknown Product',
            $item->branch?->name ?? 'Unknown Branch',
            $item->batch_number,
            $item->quantity,
            $item->expiry_date?->format('Y-m-d') ?? 'None',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge the first row for personnel
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
        ]);

        // Header row (column titles)
        $sheet->fromArray(['Product Name','Branch','Batch Number','Quantity','Expiry Date'], null, 'A2');
        $sheet->getStyle('A2:E2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '4A90E2']],
        ]);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Body styling
        $sheet->getStyle('A3:E10000')->applyFromArray([
            'font' => ['size' => 12],
        ]);

        return [];
    }
}
