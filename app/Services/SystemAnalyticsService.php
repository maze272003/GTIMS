<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Hold;
use App\Models\HistoryLog;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SystemAnalyticsService
{
    /**
     * Get the appropriate SQL date grouping expression for the current database driver.
     */
    private function getDateGroupExpression(string $column, string $groupBy): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return match ($groupBy) {
                'week'  => "strftime('%Y-W%W', {$column})",
                'month' => "strftime('%Y-%m', {$column})",
                default => "date({$column})",
            };
        }

        // MySQL / MariaDB
        return match ($groupBy) {
            'week'  => "DATE_FORMAT({$column}, '%x-W%v')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE({$column})",
        };
    }

    /**
     * Get inventory movement trends over time (for line/bar charts).
     *
     * Returns daily aggregated IN/OUT quantities within the given date range.
     */
    public function getInventoryMovementTrends(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $branchId = null,
        string $groupBy = 'day'
    ): array {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $dateFormat = $this->getDateGroupExpression('product_movements.created_at', $groupBy);

        $query = ProductMovement::query()
            ->select(
                DB::raw("{$dateFormat} as period"),
                DB::raw("SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as total_out"),
                DB::raw("COUNT(*) as movement_count")
            )
            ->whereBetween('product_movements.created_at', [$from, $to]);

        if ($branchId) {
            $query->whereHas('inventory', fn ($q) => $q->where('branch_id', $branchId));
        }

        $results = $query->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'data' => $results,
        ];
    }

    /**
     * Get stock level distribution across products (for bar/pie charts).
     *
     * Returns current stock quantities per product with available vs held breakdown.
     */
    public function getStockLevelDistribution(?int $branchId = null): array
    {
        $query = Inventory::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_on_hand')
            )
            ->where('is_archived', false)
            ->whereHas('product', fn ($q) => $q->where('is_archived', false));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $stocks = $query->groupBy('product_id')
            ->with('product:id,generic_name,brand_name')
            ->get();

        $distribution = [];
        foreach ($stocks as $stock) {
            $heldQuantity = DB::table('hold_items')
                ->join('holds', 'holds.id', '=', 'hold_items.hold_id')
                ->join('inventories', 'inventories.id', '=', 'hold_items.inventory_id')
                ->where('inventories.product_id', $stock->product_id)
                ->whereIn('holds.status', ['pending', 'approved'])
                ->when($branchId, fn ($q) => $q->where('inventories.branch_id', $branchId))
                ->sum('hold_items.quantity');

            $distribution[] = [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->generic_name ?? $stock->product->brand_name ?? 'Unknown',
                'total_on_hand' => (int) $stock->total_on_hand,
                'held' => (int) $heldQuantity,
                'available' => max(0, (int) $stock->total_on_hand - (int) $heldQuantity),
            ];
        }

        usort($distribution, fn ($a, $b) => $b['total_on_hand'] <=> $a['total_on_hand']);

        return $distribution;
    }

    /**
     * Get expiry tracking data (for timeline/bar charts).
     *
     * Returns inventory batches grouped by expiry timeframe.
     */
    public function getExpiryTracking(?int $branchId = null): array
    {
        $today = Carbon::today();
        $query = Inventory::query()
            ->where('is_archived', false)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereHas('product', fn ($q) => $q->where('is_archived', false));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $batches = $query->with('product:id,generic_name,brand_name')->get();

        $expired = [];
        $within30Days = [];
        $within90Days = [];
        $within180Days = [];
        $beyond180Days = [];

        foreach ($batches as $batch) {
            $expiryDate = $batch->expiry_date->copy()->startOfDay();
            $daysUntilExpiry = (int) round($today->floatDiffInDays($expiryDate, false));

            $item = [
                'inventory_id' => $batch->id,
                'product_name' => $batch->product->generic_name ?? $batch->product->brand_name ?? 'Unknown',
                'batch_number' => $batch->batch_number,
                'quantity' => (int) $batch->quantity,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'days_until_expiry' => $daysUntilExpiry,
            ];

            if ($daysUntilExpiry < 0) {
                $expired[] = $item;
            } elseif ($daysUntilExpiry <= 30) {
                $within30Days[] = $item;
            } elseif ($daysUntilExpiry <= 90) {
                $within90Days[] = $item;
            } elseif ($daysUntilExpiry <= 180) {
                $within180Days[] = $item;
            } else {
                $beyond180Days[] = $item;
            }
        }

        return [
            'summary' => [
                'expired' => count($expired),
                'within_30_days' => count($within30Days),
                'within_90_days' => count($within90Days),
                'within_180_days' => count($within180Days),
                'beyond_180_days' => count($beyond180Days),
            ],
            'expired' => $expired,
            'within_30_days' => $within30Days,
            'within_90_days' => $within90Days,
            'within_180_days' => $within180Days,
            'beyond_180_days' => $beyond180Days,
        ];
    }

    /**
     * Get request status distribution (for pie/donut charts).
     */
    public function getRequestStatusDistribution(?int $branchId = null): array
    {
        $query = IncomingRequest::query()
            ->select('status', DB::raw('COUNT(*) as count'));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $distribution = $query->groupBy('status')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        $total = array_sum(array_column($distribution, 'count'));

        return [
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    /**
     * Get request volume trends over time (for line charts).
     */
    public function getRequestVolumeTrends(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $branchId = null,
        string $groupBy = 'day'
    ): array {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $dateFormat = $this->getDateGroupExpression('incoming_requests.created_at', $groupBy);

        $query = IncomingRequest::query()
            ->select(
                DB::raw("{$dateFormat} as period"),
                DB::raw("COUNT(*) as total_requests"),
                DB::raw("SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count"),
                DB::raw("SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count"),
                DB::raw("SUM(CASE WHEN priority = 'normal' THEN 1 ELSE 0 END) as normal_count"),
                DB::raw("SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_count")
            )
            ->whereBetween('incoming_requests.created_at', [$from, $to]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $results = $query->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'data' => $results,
        ];
    }

    /**
     * Get hold analytics (for pie/bar charts).
     *
     * Returns hold distribution by status and type.
     */
    public function getHoldAnalytics(?int $branchId = null): array
    {
        $baseQuery = Hold::query();
        if ($branchId) {
            $baseQuery->where('branch_id', $branchId);
        }

        $byStatus = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        $byType = (clone $baseQuery)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        $totalHolds = (clone $baseQuery)->count();

        return [
            'total' => $totalHolds,
            'by_status' => $byStatus,
            'by_type' => $byType,
        ];
    }

    /**
     * Get user activity trends from audit events (for line/bar charts).
     */
    public function getUserActivityTrends(
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $groupBy = 'day'
    ): array {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $dateFormat = $this->getDateGroupExpression('audit_events.created_at', $groupBy);

        $activityByPeriod = AuditEvent::query()
            ->select(
                DB::raw("{$dateFormat} as period"),
                DB::raw('COUNT(*) as total_events'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->whereBetween('audit_events.created_at', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'data' => $activityByPeriod,
        ];
    }

    /**
     * Get audit event distribution by action and entity type (for pie/bar charts).
     */
    public function getAuditEventDistribution(
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $byAction = AuditEvent::query()
            ->select('action', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        $byEntity = AuditEvent::query()
            ->select('entity_type', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('entity_type')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by_action' => $byAction,
            'by_entity' => $byEntity,
        ];
    }

    /**
     * Get inventory turnover metrics (for bar charts).
     *
     * Calculates movement velocity per product over the given period.
     */
    public function getInventoryTurnover(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $branchId = null
    ): array {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $query = ProductMovement::query()
            ->select(
                'product_movements.product_id',
                DB::raw("SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as total_out"),
                DB::raw('COUNT(*) as movement_count')
            )
            ->whereBetween('product_movements.created_at', [$from, $to]);

        if ($branchId) {
            $query->whereHas('inventory', fn ($q) => $q->where('branch_id', $branchId));
        }

        $movements = $query->groupBy('product_movements.product_id')
            ->get();

        $turnover = [];
        foreach ($movements as $m) {
            $product = Product::find($m->product_id);
            if (! $product || $product->is_archived) {
                continue;
            }

            $currentStock = Inventory::where('product_id', $m->product_id)
                ->where('is_archived', false)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('quantity');

            $avgStock = $currentStock > 0 ? $currentStock : 1;
            $turnoverRate = round((int) $m->total_out / $avgStock, 2);

            $turnover[] = [
                'product_id' => $m->product_id,
                'product_name' => $product->generic_name ?? $product->brand_name ?? 'Unknown',
                'total_in' => (int) $m->total_in,
                'total_out' => (int) $m->total_out,
                'movement_count' => (int) $m->movement_count,
                'current_stock' => (int) $currentStock,
                'turnover_rate' => $turnoverRate,
            ];
        }

        usort($turnover, fn ($a, $b) => $b['turnover_rate'] <=> $a['turnover_rate']);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'data' => $turnover,
        ];
    }

    /**
     * Get a comprehensive system overview with all key metrics (dashboard summary).
     */
    public function getSystemOverview(?int $branchId = null): array
    {
        $totalProducts = Product::where('is_archived', false)->count();
        $totalBatches = Inventory::where('is_archived', false)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();
        $totalStock = Inventory::where('is_archived', false)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('quantity');

        $expiringIn30 = Inventory::where('is_archived', false)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->where('expiry_date', '>', Carbon::now())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $expiredBatches = Inventory::where('is_archived', false)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $pendingRequests = IncomingRequest::whereIn('status', ['draft', 'requested', 'review'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $activeHolds = Hold::whereIn('status', ['pending', 'approved'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $todayMovements = ProductMovement::whereDate('created_at', Carbon::today())->count();

        $recentAuditCount = AuditEvent::where('created_at', '>=', Carbon::now()->subDay())->count();

        return [
            'total_products' => $totalProducts,
            'total_batches' => $totalBatches,
            'total_stock' => (int) $totalStock,
            'expiring_in_30_days' => $expiringIn30,
            'expired_batches' => $expiredBatches,
            'pending_requests' => $pendingRequests,
            'active_holds' => $activeHolds,
            'today_movements' => $todayMovements,
            'recent_audit_events' => $recentAuditCount,
        ];
    }
}
