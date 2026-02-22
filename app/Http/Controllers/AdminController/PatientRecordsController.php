<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\PatientRecordsAdminService;
use Illuminate\Http\Request;

class PatientRecordsController extends Controller
{
    public function __construct(
        protected PatientRecordsAdminService $patientRecordsAdminService
    ) {
    }

    public function showpatientrecords(Request $request)
    {
        return $this->patientRecordsAdminService->showpatientrecords($request);
    }

    public function adddispensation(Request $request)
    {
        return $this->patientRecordsAdminService->adddispensation($request);
    }

    public function updatePatientRecord(Request $request)
    {
        return $this->patientRecordsAdminService->updatePatientRecord($request);
    }

    public function exportPdf(Request $request)
    {
        return $this->patientRecordsAdminService->exportPdf($request);
    }

    public function exportExcel(Request $request)
    {
        return $this->patientRecordsAdminService->exportExcel($request);
    }
}

