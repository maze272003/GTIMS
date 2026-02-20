<?php

namespace App\Exports;

use App\Models\ProductMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProductMovementsExport implements 
    FromQuery, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithDrawings, 
    WithCustomStartCell, 
    WithEvents
{
    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Letterhead');
        $drawing->setDescription('Official Header');
        
        $path = public_path('images/letterhead.png'); 
        if (!file_exists($path)) {
            $path = public_path('letterhead.png');
        }

        if (file_exists($path)) {
            $drawing->setPath($path);
            $drawing->setWidth(1485); 
            $drawing->setCoordinates('A1');
            $drawing->setOffsetY(5);
            $drawing->setOffsetX(10);
            return $drawing;
        }

        return [];
    }

    public function startCell(): string
    {
        return 'A19'; 
    }

    public function query()
    {
        $query = ProductMovement::with(['product', 'user', 'inventory.branch'])
            ->orderBy('created_at', $this->params['sort'] ?? 'desc');

        if (!empty($this->params['search'])) {
            $search = $this->params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('inventory', fn($q_inv) => $q_inv->where('batch_number', 'like', "%{$search}%"));
            });
        }

        if (!empty($this->params['product_id'])) $query->where('product_id', $this->params['product_id']);
        if (!empty($this->params['type'])) $query->where('type', $this->params['type']);
        if (!empty($this->params['user_id'])) $query->where('user_id', $this->params['user_id']);
        if (!empty($this->params['branch_id'])) {
            $query->whereHas('inventory', fn($q) => $q->where('branch_id', $this->params['branch_id']));
        }
        if (!empty($this->params['from'])) {
            $query->where('created_at', '>=', Carbon::parse($this->params['from'])->startOfDay());
        }
        if (!empty($this->params['to'])) {
            $query->where('created_at', '<=', Carbon::parse($this->params['to'])->endOfDay());
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Date & Time',
            'Branch',
            'Product Name',
            'Batch #',
            'Type',
            'Qty Change',    // This will be red
            'Before',
            'After',
            'Description',
            'User'
        ];
    }

    public function map($movement): array
    {
        $qty = $movement->quantity;
        if ($movement->type === 'OUT') {
            $qty = -1 * abs($qty);
        }

        return [
            $movement->created_at->format('M d, Y h:i A'),
            $movement->inventory->branch->name ?? 'N/A', 
            $movement->product->generic_name . ' (' . $movement->product->brand_name . ')',
            $movement->inventory->batch_number ?? 'N/A',
            $movement->type,
            $qty,
            $movement->quantity_before ?? '0',
            $movement->quantity_after ?? '0',
            $movement->description,
            $movement->user->name ?? 'System',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Title
                $sheet->mergeCells('A16:J16');
                $sheet->setCellValue('A16', 'Product Movement Report');
                $sheet->getStyle('A16')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Exported By + Generated Date — RIGHT AFTER TITLE (Row 17)
                $user = Auth::user()?->name ?? 'Guest';
                $generatedAt = now()->format('M d, Y h:i A');
                $exportInfo = "Exported By: $user";

                $sheet->mergeCells('A17:J17');
                $sheet->setCellValue('A17', $exportInfo);
                $sheet->getStyle('A17')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Only Generated Timestamp at the very bottom
                $footerRow = $highestRow + 3;
                $sheet->mergeCells("A$footerRow:J$footerRow");
                $sheet->setCellValue("A$footerRow", "Generated: $generatedAt");
                $sheet->getStyle("A$footerRow")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Qty Change column: Red if negative, Dark Green if positive
                for ($row = 20; $row <= $highestRow; $row++) {
                    $qtyValue = $sheet->getCell("F$row")->getValue();
                    if ($qtyValue < 0) {
                        $sheet->getStyle("F$row")->getFont()->setColor(new Color(Color::COLOR_RED));
                    } else {
                        $sheet->getStyle("F$row")->getFont()->setColor(new Color(Color::COLOR_DARKGREEN));
                    }
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header row styling (row 19)
        $sheet->getStyle('A19:J19')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'E5E7EB']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);

        // Make "Qty Change" header RED
        $sheet->getStyle('F19')->getFont()->setColor(new Color(Color::COLOR_RED));

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders to data rows
        $lastRow = $sheet->getHighestRow();
        if ($lastRow >= 20) {
            $sheet->getStyle("A19:J$lastRow")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]);
        }
    }
}