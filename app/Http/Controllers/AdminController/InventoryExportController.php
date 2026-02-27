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
        $branch = $request->input('branch') ?? $request->user()?->branch_id ?? 1;

        return $this->inventoryExportService->export(
            $branch,
            $request->input('filter'),
            $request->input('search')
        );
    }
}
