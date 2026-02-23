<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\InventoryExportService;
use Illuminate\Http\Request;

class InventoryExportController extends Controller
{
    public function __construct(
        protected InventoryExportService $inventoryExportService
    ) {
    }

    public function export(Request $request)
    {
        return $this->inventoryExportService->export(
            $request->input('branch'),
            $request->input('filter'),
            $request->input('search')
        );
    }
}

