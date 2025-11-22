<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\InventoryExport;
use Maatwebsite\Excel\Facades\Excel;

class InventoryExportController extends Controller
{
    public function export(Request $request)
    {
        $branch = $request->input('branch'); // e.g., 1 or 2
        $fileName = 'inventory_rhu' . $branch . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new InventoryExport($branch), $fileName);
    }
}
