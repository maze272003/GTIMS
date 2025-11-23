<?php

namespace App\Exports;

use App\Models\Patientrecords;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PatientRecordsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;
    protected $user;

    // Constructor to receive filters and user data from the Controller
    public function __construct($filters, $user)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function query()
    {
        $query = Patientrecords::with(['dispensedMedications', 'barangay', 'branch']);
        $filters = $this->filters;
        $user = $this->user;

        // --- 1. Branch Logic (Same as Controller) ---
        if (in_array($user->user_level_id, [1, 2])) {
            if (isset($filters['branch_filter']) && $filters['branch_filter'] !== 'all') {
                $query->where('branch_id', $filters['branch_filter']);
            }
        } else {
            $query->where('branch_id', $user->branch_id);
        }

        // --- 2. Date & Category Filters ---
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

        return $query->latest();
    }

    // Define the Excel Headers
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
            'Medications (Qty)', // We will combine medications into one cell
        ];
    }

    // Map the data for each row
    public function map($record): array
    {
        // Format medications list as a string (e.g., "Paracetamol (10), Amoxicillin (5)")
        $meds = $record->dispensedMedications->map(function($med) {
            return $med->generic_name . ' (' . $med->quantity . ')';
        })->implode(', ');

        return [
            $record->id,
            $record->patient_name,
            $record->barangay->barangay_name ?? 'N/A',
            $record->purok,
            $record->category,
            $record->branch->name ?? 'N/A',
            $record->date_dispensed->format('Y-m-d'),
            $meds ?: 'No medications',
        ];
    }

    // Optional: Bold the header row
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}