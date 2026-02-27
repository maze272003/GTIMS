<?php

namespace App\Exports;

use App\Models\Supplier;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SuppliersExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithDrawings,
    WithCustomStartCell,
    WithEvents
{
    public function __construct(
        protected $user,
        protected ?TenantContext $tenantContext = null,
    ) {
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
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(5);

            return $drawing;
        }

        return [];
    }

    public function startCell(): string
    {
        return 'A19';
    }

    public function collection()
    {
        $query = Supplier::query()
            ->with([
                'supplierProducts' => fn ($query) => $query
                    ->when($this->tenantContext && !$this->tenantContext->isPlatform(), function ($supplierProductQuery) {
                        $supplierProductQuery->whereHas('inventory', function ($inventoryQuery) {
                            TenantScope::apply($inventoryQuery, $this->tenantContext, 'inventories');
                        });
                    })
                    ->with(['inventory.product', 'inventory.branch'])
                    ->orderBy('id'),
            ])
            ->withCount(['supplierProducts as linked_batches_count'])
            ->orderBy('name');

        TenantScope::apply($query, $this->tenantContext);

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Supplier ID',
            'Supplier Name',
            'Contact Person',
            'Email',
            'Phone',
            'Address',
            'Status',
            'Linked Batches',
            'Linked Products',
            'Batch / Inventory Details',
            'Created At',
        ];
    }

    public function map($supplier): array
    {
        $distinctProductIds = $supplier->supplierProducts
            ->pluck('inventory.product_id')
            ->filter()
            ->unique()
            ->count();

        $batchDetails = $supplier->supplierProducts
            ->map(function ($link) {
                $inventory = $link->inventory;
                $product = $inventory?->product;
                $branch = $inventory?->branch;

                $productLabel = $product?->generic_name ?? $product?->brand_name ?? 'Unknown Product';
                $branchLabel = $branch?->name ?? 'Unknown Branch';
                $batchNumber = $inventory?->batch_number ?? 'N/A';
                $qty = $inventory?->quantity ?? 'N/A';
                $leadTime = $link->lead_time_days ?? '-';
                $cost = $link->unit_cost !== null
                    ? 'PHP ' . number_format((float) $link->unit_cost, 2)
                    : '-';

                return "{$productLabel} | {$branchLabel} | Batch: {$batchNumber} | Qty: {$qty} | Lead: {$leadTime}d | Cost: {$cost}";
            })
            ->implode("\n");

        return [
            $supplier->id,
            $supplier->name,
            $supplier->contact_person ?? 'N/A',
            $supplier->email ?? 'N/A',
            $supplier->phone ?? 'N/A',
            $supplier->address ?? 'N/A',
            $supplier->is_active ? 'Active' : 'Inactive',
            (int) ($supplier->linked_batches_count ?? $supplier->supplierProducts->count()),
            $distinctProductIds,
            $batchDetails !== '' ? $batchDetails : 'No linked inventory batches',
            optional($supplier->created_at)->format('M d, Y h:i A') ?? 'N/A',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('A16:K16');
                $sheet->setCellValue('A16', 'Suppliers Report');
                $sheet->getStyle('A16')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $userName = Auth::check() ? Auth::user()->name : ($this->user->name ?? 'User');
                $sheet->mergeCells('A17:K17');
                $sheet->setCellValue('A17', "Exported By: {$userName}");
                $sheet->getStyle('A17')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $footerRow = $highestRow + 3;
                $sheet->mergeCells("A{$footerRow}:K{$footerRow}");
                $sheet->setCellValue("A{$footerRow}", 'Generated: ' . now()->format('M d, Y h:i A'));
                $sheet->getStyle("A{$footerRow}")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A19:K19')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'E5E7EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        $lastRow = $sheet->getHighestRow();
        if ($lastRow >= 20) {
            $sheet->getStyle("A19:K{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);

            $sheet->getStyle("A20:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G20:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J20:J{$lastRow}")->getAlignment()->setWrapText(true);
        }

        return [];
    }
}
