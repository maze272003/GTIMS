<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\InventoryExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryExportController extends Controller
{
    public function __construct(
        protected InventoryExportService $inventoryExportService
    ) {
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'branch' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'filter' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        return $this->inventoryExportService->export(
            (int) $validated['branch'],
            $validated['filter'] ?? null,
            $validated['search'] ?? null
        );
    }
}
