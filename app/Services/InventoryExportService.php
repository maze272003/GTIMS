<?php

namespace App\Services;

use App\Exports\InventoryExport;
use App\Models\Branch;
use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use App\Repositories\Interfaces\InventoryAdminRepositoryInterface;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryExportService
{
    public function __construct(
        protected HistoryLogRepositoryInterface $historyLogRepository,
        protected InventoryAdminRepositoryInterface $inventoryAdminRepository,
        protected BranchAccessService $branchAccessService
    ) {
    }

    public function export(int|string $branch, ?string $filter = null, ?string $search = null): BinaryFileResponse
    {
        $branch = $this->branchAccessService->resolveBranchFilter(auth()->user(), $branch);
        $branchModel = Branch::query()->findOrFail((int) $branch);
        $branchSlug = $branchModel->code ?: 'branch-'.$branchModel->id;
        $fileName = 'inventory_' . $branchSlug . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $user = auth()->user();

        $this->historyLogRepository->create([
            'action' => 'INVENTORY EXPORTED',
            'description' => "Inventory for {$branchModel->name}" . ($filter || $search ? ' (filtered)' : '') . ' has been exported.',
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'metadata' => [
                'branch_id' => $branchModel->id,
                'branch_name' => $branchModel->name,
            ],
        ]);

        $query = $this->inventoryAdminRepository->buildActiveInventoryByBranchQuery(
            (int) $branchModel->id,
            $search,
            $filter
        );

        return Excel::download(new InventoryExport($query, $branchModel->name, $filter, $search), $fileName);
    }
}
