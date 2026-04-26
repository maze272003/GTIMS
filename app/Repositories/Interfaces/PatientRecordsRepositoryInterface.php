<?php

namespace App\Repositories\Interfaces;

use App\Models\Inventory;
use App\Models\Patientrecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface PatientRecordsRepositoryInterface
{
    public function patientRecordsQuery(): Builder;

    public function getActiveInventoriesWithProduct(?array $branchIds = null): Collection;

    public function getAllBarangays(): Collection;

    public function getAllBranches(?array $branchIds = null): Collection;

    public function findInventoryWithProductOrFail(int $id): Inventory;

    public function createPatientRecord(array $data): Patientrecords;

    public function createHistoryLog(array $data): void;

    public function createProductMovement(array $data): void;

    public function createDispensedMedication(array $data): void;

    public function findPatientRecordWithBarangayOrFail(int $id): Patientrecords;

    public function updateDispensedMedicationsBarangay(int $patientRecordId, int $barangayId): int;
}
