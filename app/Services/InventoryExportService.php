<?php

namespace App\Services;

use App\Exports\InventoryExport;
use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryExportService
{
    public function __construct(
        protected HistoryLogRepositoryInterface $historyLogRepository
    ) {
    }

    public function export(int|string $branch, ?string $filter = null, ?string $search = null): BinaryFileResponse
    {
        $fileName = 'inventory_rhu' . $branch . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $user = auth()->user();

        $this->historyLogRepository->create([
            'action' => 'INVENTORY EXPORTED',
            'description' => "Inventory for RHU {$branch}" . ($filter || $search ? ' (filtered)' : '') . ' has been exported.',
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
        ]);

        return Excel::download(new InventoryExport($branch, $filter, $search), $fileName);
    }
}

