<?php

namespace App\Services;

use App\Exports\InventoryExport;
use App\Tenancy\TenantContext;
use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryExportService
{
    public function __construct(
        protected HistoryLogRepositoryInterface $historyLogRepository,
        protected TenantExportService $tenantExportService,
    ) {
    }

    public function export(int|string $branch, ?string $filter = null, ?string $search = null): BinaryFileResponse
    {
        $fileName = 'inventory_rhu' . $branch . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $user = auth()->user();
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        $this->historyLogRepository->create([
            'action' => 'INVENTORY EXPORTED',
            'description' => "Inventory for RHU {$branch}" . ($filter || $search ? ' (filtered)' : '') . ' has been exported.',
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'province_id' => $tenantContext?->provinceId,
            'barangay_id' => $tenantContext?->barangayId,
        ]);

        $stored = $this->tenantExportService->store(
            new InventoryExport($branch, $filter, $search, $tenantContext),
            $fileName,
            $tenantContext
        );

        return $this->tenantExportService->download($stored);
    }
}
