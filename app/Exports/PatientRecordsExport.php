<?php

namespace App\Exports;

use App\Models\Patientrecords;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Facades\Auth;

class PatientRecordsExport implements 
    FromQuery, 
    WithHeadings, 
    WithMapping, 
    ShouldAutoSize, 
    WithStyles,
    WithDrawings,
    WithCustomStartCell,
    WithEvents
{
    protected $filters;
    protected $user;
    protected ?TenantContext $tenantContext;

    public function __construct($filters, $user, ?TenantContext $tenantContext = null)
    {
        $this->filters = $filters;
        $this->user = $user;
        $this->tenantContext = $tenantContext;
    }

    // Letterhead Image
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
        return 'A19'; // Data starts here
    }

    public function query()
    {
        $query = Patientrecords::with(['dispensedMedications', 'barangay', 'branch']);
        $filters = $this->filters;
        $user = $this->user;

        // Branch Logic (Same as Controller)
        if ($this->tenantContext && !$this->tenantContext->isPlatform()) {
            TenantScope::apply($query, $this->tenantContext);
        } elseif ($user->hasPermission('patients.manage')) {
            if (isset($filters['branch_filter']) && $filters['branch_filter'] !== 'all') {
                $query->where('branch_id', $filters['branch_filter']);
            }
        } else {
            $query->where('branch_id', $user->branch_id);
        }

        // Date & Category Filters
        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['barangay_id'])) {
            $query->where('barangay_id', $filters['barangay_id']);
        }

        return $query->latest(); // Already ordered by latest
    }

    public function headings(): array
    {
        return [
            'Record ID',
            'Patient Name',
            'Barangay',
            'Purok',
            'Category',
            'Branch',
            'Date Dispensed',
            'Medications (Qty)',
        ];
    }

    public function map($record): array
    {
        $meds = $record->dispensedMedications->map(function($med) {
            return $med->generic_name . ' (' . $med->quantity . ')';
        })->implode(', ');

        return [
            $record->id,
            $record->patient_name,
            $record->barangay->barangay_name ?? 'N/A',
            $record->purok ?? 'N/A',
            $record->category,
            $record->branch->name ?? 'N/A',
            $record->date_dispensed?->format('M d, Y') ?? 'N/A',
            $meds ?: 'No medications',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Title
                $sheet->mergeCells('A16:H16');
                $sheet->setCellValue('A16', 'Patient Dispensing Records Report');
                $sheet->getStyle('A16')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Exported By + Generated (below title)
                $userName = Auth::check() ? Auth::user()->name : $this->user->name ?? 'User';
                $generatedAt = now()->format('M d, Y h:i A');
                $exportInfo = "Exported By: $userName";

                $sheet->mergeCells('A17:H17');
                $sheet->setCellValue('A17', $exportInfo);
                $sheet->getStyle('A17')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Generated timestamp only at the bottom
                $footerRow = $highestRow + 3;
                $sheet->mergeCells("A$footerRow:H$footerRow");
                $sheet->setCellValue("A$footerRow", "Generated: $generatedAt");
                $sheet->getStyle("A$footerRow")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header Row (Row 19)
        $sheet->getStyle('A19:H19')->applyFromArray([
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

        // Add borders to all data rows
        $lastRow = $sheet->getHighestRow();
        if ($lastRow >= 20) {
            $sheet->getStyle("A19:H$lastRow")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);
        }

        // Wrap text for Medications column
        $sheet->getStyle('H19:H' . $lastRow)->getAlignment()->setWrapText(true);
    }
}
