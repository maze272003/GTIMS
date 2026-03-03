<?php

namespace App\Services;

use App\Exports\InventoryExport;
use App\Exports\ProductMovementsExport;
use App\Models\Branch;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class WorkflowReportService
{
    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $context
     * @return array{report_type:string,file_name:string,disk:string,path:string,mime_type:string}
     */
    public function generate(array $config, array $context = []): array
    {
        $reportType = (string) ($config['report_type'] ?? 'inventory_summary');
        $reportType = trim($reportType) !== '' ? trim($reportType) : 'inventory_summary';

        $disk = 'local';
        $fileName = $this->buildFileName($reportType);
        $path = 'automation-reports/' . $fileName;

        match ($reportType) {
            'stock_movement' => $this->storeStockMovementReport($path, $disk, $config, $context),
            'expiry_report' => $this->storeInventoryReport($path, $disk, 'nearly_expired', $config, $context),
            'low_stock' => $this->storeInventoryReport($path, $disk, 'low_stock', $config, $context),
            'inventory_summary' => $this->storeInventoryReport($path, $disk, null, $config, $context),
            default => $this->storeInventoryReport($path, $disk, null, $config, $context),
        };

        return [
            'report_type' => $reportType,
            'file_name' => $fileName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $context
     */
    protected function storeStockMovementReport(string $path, string $disk, array $config, array $context): void
    {
        $filters = [];
        foreach (['product_id', 'type', 'user_id', 'from', 'to', 'search'] as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                $filters[$key] = $config[$key];
                continue;
            }

            if (array_key_exists($key, $context) && $context[$key] !== null && $context[$key] !== '') {
                $filters[$key] = $context[$key];
            }
        }

        $sort = strtolower((string) ($config['sort'] ?? $context['sort'] ?? 'desc'));
        $filters['sort'] = in_array($sort, ['asc', 'desc'], true) ? $sort : 'desc';

        $branchId = $this->resolveOptionalBranchId($config, $context);
        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        if (!Excel::store(new ProductMovementsExport($filters), $path, $disk)) {
            throw new RuntimeException('Failed to generate stock movement report.');
        }
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $context
     */
    protected function storeInventoryReport(string $path, string $disk, ?string $filter, array $config, array $context): void
    {
        $branchId = $this->resolveBranchId($config, $context);
        $search = null;

        if (isset($config['search']) && !is_array($config['search'])) {
            $search = trim((string) $config['search']);
        } elseif (isset($context['search']) && !is_array($context['search'])) {
            $search = trim((string) $context['search']);
        }

        if ($search === '') {
            $search = null;
        }

        if (!Excel::store(new InventoryExport($branchId, $filter, $search), $path, $disk)) {
            throw new RuntimeException('Failed to generate inventory report.');
        }
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $context
     */
    protected function resolveBranchId(array $config, array $context): int
    {
        $candidate = $this->resolveOptionalBranchId($config, $context);
        if ($candidate !== null) {
            return $candidate;
        }

        $fallback = Branch::query()
            ->active()
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->value('id');

        if ($fallback === null) {
            throw new RuntimeException('Cannot generate report: no active branch was found.');
        }

        return (int) $fallback;
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $context
     */
    protected function resolveOptionalBranchId(array $config, array $context): ?int
    {
        $candidate = $config['branch_id'] ?? $context['branch_id'] ?? null;
        if (!is_numeric($candidate)) {
            return null;
        }

        $branchId = (int) $candidate;
        if ($branchId <= 0) {
            return null;
        }

        return Branch::query()->whereKey($branchId)->exists() ? $branchId : null;
    }

    protected function buildFileName(string $reportType): string
    {
        $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($reportType)) ?? 'report';
        $safeType = trim($safeType, '_');
        if ($safeType === '') {
            $safeType = 'report';
        }

        return "automation_{$safeType}_" . now()->format('Ymd_His') . '.xlsx';
    }
}
