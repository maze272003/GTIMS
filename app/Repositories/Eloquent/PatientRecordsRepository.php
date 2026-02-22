<?php

namespace App\Repositories\Eloquent;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Dispensedmedication;
use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use App\Repositories\Interfaces\PatientRecordsRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PatientRecordsRepository implements PatientRecordsRepositoryInterface
{
    public function patientRecordsQuery(): Builder
    {
        return Patientrecords::query();
    }

    public function getActiveInventoriesWithProduct(): Collection
    {
        return Inventory::with('product')->where('is_archived', 0)->latest()->get();
    }

    public function getAllBarangays(): Collection
    {
        return Barangay::all();
    }

    public function getAllBranches(): Collection
    {
        return Branch::all();
    }

    public function findInventoryWithProductOrFail(int $id): Inventory
    {
        return Inventory::with('product')->findOrFail($id);
    }

    public function createPatientRecord(array $data): Patientrecords
    {
        return Patientrecords::create($data);
    }

    public function createHistoryLog(array $data): void
    {
        HistoryLog::create($data);
    }

    public function createProductMovement(array $data): void
    {
        ProductMovement::create($data);
    }

    public function createDispensedMedication(array $data): void
    {
        Dispensedmedication::create($data);
    }

    public function findPatientRecordWithBarangayOrFail(int $id): Patientrecords
    {
        return Patientrecords::with('barangay')->findOrFail($id);
    }

    public function updateDispensedMedicationsBarangay(int $patientRecordId, int $barangayId): int
    {
        return Dispensedmedication::where('patientrecord_id', $patientRecordId)
            ->update(['barangay_id' => $barangayId]);
    }
}

