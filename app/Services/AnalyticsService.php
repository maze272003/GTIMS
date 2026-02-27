<?php

namespace App\Services;

use App\Models\IncomingRequest;
use App\Models\LowStockSetting;
use App\Models\ReorderRule;
use App\Models\Product;
use App\Models\Inventory;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;

class AnalyticsService
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    public function getRequestSLAMetrics(?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null, ?TenantContext $tenantContext = null): array
    {
        $tenantContext = $tenantContext ?: $this->resolveCurrentTenantContext();
        $query = TenantScope::apply(IncomingRequest::query(), $tenantContext);

        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<=', $to);

        $requests = $query->with('statusHistory')->get();

        $metrics = [
            'total_requests' => $requests->count(),
            'avg_cycle_time_hours' => 0,
            'avg_approval_time_hours' => 0,
            'avg_fulfillment_time_hours' => 0,
        ];

        $cycleTimes = [];
        $approvalTimes = [];
        $fulfillmentTimes = [];

        foreach ($requests as $request) {
            $history = $request->statusHistory->sortBy('created_at');

            $requested = $history->where('new_status', 'requested')->first();
            $approved = $history->where('new_status', 'approved')->first();
            $fulfilled = $history->where('new_status', 'fulfilled')->first();
            $closed = $history->where('new_status', 'closed')->first();

            if ($requested && $closed) {
                $cycleTimes[] = $requested->created_at->diffInHours($closed->created_at);
            }

            if ($requested && $approved) {
                $approvalTimes[] = $requested->created_at->diffInHours($approved->created_at);
            }

            if ($approved && $fulfilled) {
                $fulfillmentTimes[] = $approved->created_at->diffInHours($fulfilled->created_at);
            }
        }

        if (count($cycleTimes) > 0) {
            $metrics['avg_cycle_time_hours'] = round(array_sum($cycleTimes) / count($cycleTimes), 2);
        }
        if (count($approvalTimes) > 0) {
            $metrics['avg_approval_time_hours'] = round(array_sum($approvalTimes) / count($approvalTimes), 2);
        }
        if (count($fulfillmentTimes) > 0) {
            $metrics['avg_fulfillment_time_hours'] = round(array_sum($fulfillmentTimes) / count($fulfillmentTimes), 2);
        }

        return $metrics;
    }

    public function getReorderSuggestions(?int $branchId = null, ?TenantContext $tenantContext = null): array
    {
        $tenantContext = $tenantContext ?: $this->resolveCurrentTenantContext();

        $rulesQuery = ReorderRule::with(['product', 'preferredSupplier']);
        TenantScope::apply($rulesQuery, $tenantContext);

        if ((!$tenantContext || $tenantContext->isPlatform()) && $branchId) {
            $rulesQuery->where('branch_id', $branchId);
        }

        $rules = $rulesQuery->get();

        $suggestions = [];

        foreach ($rules as $rule) {
            $available = $this->availabilityService->getAvailable(
                $rule->product_id,
                $rule->branch_id,
                $tenantContext
            );

            if ($available <= $rule->reorder_point) {
                $suggestions[] = [
                    'product' => $rule->product,
                    'branch_id' => $rule->branch_id,
                    'current_available' => $available,
                    'reorder_point' => $rule->reorder_point,
                    'suggested_quantity' => $rule->reorder_quantity,
                    'preferred_supplier' => $rule->preferredSupplier,
                    'lead_time_days' => $rule->preferredSupplier
                        ? $rule->product?->supplierProducts()
                            ->where('supplier_id', $rule->preferred_supplier_id)
                            ->orderBy('lead_time_days')
                            ->value('lead_time_days')
                        : null,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get low stock alerts, optionally scoped by tenant or branch.
     */
    public function getLowStockAlerts(?int $branchId = null, ?TenantContext $tenantContext = null): array
    {
        $tenantContext = $tenantContext ?: $this->resolveCurrentTenantContext();

        $globalThreshold = (int) (LowStockSetting::where('is_global', true)->value('threshold') ?? 100);
        $rulesQuery = LowStockSetting::query()->where('is_global', false);
        TenantScope::apply($rulesQuery, $tenantContext);
        if ((!$tenantContext || $tenantContext->isPlatform()) && $branchId) {
            $rulesQuery->where('branch_id', $branchId);
        }
        $rules = $rulesQuery->get(['product_id', 'branch_id', 'threshold']);
        $ruleMap = $this->buildRuleMap($rules);

        $inventoryQuery = Inventory::query()
            ->where('is_archived', false)
            ->whereHas('product', fn($q) => $q->where('is_archived', false))
            ->with([
                'product:id,generic_name,brand_name',
                'branch:id,name',
            ])
            ->withSum([
                'holdItems as held_quantity' => function ($q) {
                    $q->whereHas('hold', fn($h) => $h->whereIn('status', ['pending', 'approved']));
                },
            ], 'quantity');

        TenantScope::apply($inventoryQuery, $tenantContext);

        if ((!$tenantContext || $tenantContext->isPlatform()) && $branchId) {
            $inventoryQuery->where('branch_id', $branchId);
        }

        $batches = $inventoryQuery->get(['id', 'product_id', 'branch_id', 'batch_number', 'quantity']);

        $alerts = [];

        foreach ($batches as $batch) {
            $held = (int) ($batch->held_quantity ?? 0);
            $available = max(0, (int) $batch->quantity - $held);
            $threshold = $this->resolveThreshold(
                (int) $batch->product_id,
                (int) ($batch->branch_id ?? 0),
                $ruleMap,
                $globalThreshold
            );

            if ($available <= $threshold) {
                $alerts[] = [
                    'inventory_id'  => (int) $batch->id,
                    'product_id'    => (int) $batch->product_id,
                    'branch_id'     => (int) ($batch->branch_id ?? 0),
                    'batch_number'  => $batch->batch_number,
                    'product_name'  => $batch->product?->generic_name
                        ?? $batch->product?->brand_name
                        ?? ("Product #".$batch->product_id),
                    'branch_name'   => $batch->branch?->name ?? 'Unknown Branch',
                    'current_stock' => (int) $available,
                    'threshold'     => (int) $threshold,
                    'on_hand'       => (int) $batch->quantity,
                    'held'          => (int) $held,
                ];
            }
        }

        usort($alerts, fn($a, $b) => $a['current_stock'] <=> $b['current_stock']);

        return $alerts;
    }

    public function getStockKPIs(?int $branchId = null, ?TenantContext $tenantContext = null): array
    {
        $tenantContext = $tenantContext ?: $this->resolveCurrentTenantContext();
        $products = Product::where('is_archived', false)->get();

        $totalOnHand = 0;
        $totalHeld = 0;
        $totalAvailable = 0;

        foreach ($products as $product) {
            $onHand = $this->availabilityService->getOnHand($product->id, $branchId, $tenantContext);
            $held = $this->availabilityService->getHeldQuantity($product->id, $branchId, $tenantContext);
            $totalOnHand += $onHand;
            $totalHeld += $held;
            $totalAvailable += max(0, $onHand - $held);
        }

        return [
            'total_on_hand' => $totalOnHand,
            'total_held' => $totalHeld,
            'total_available' => $totalAvailable,
            'product_count' => $products->count(),
        ];
    }

    // ---------------------------
    // Helpers
    // ---------------------------

    private function buildRuleMap($rules): array
    {
        // map keys:
        // p{productId}-b{branchId}
        // use 0 for NULL (all products / all branches)
        $map = [];

        foreach ($rules as $r) {
            $p = $r->product_id ?? 0;
            $b = $r->branch_id ?? 0;
            $map["p{$p}-b{$b}"] = (int) $r->threshold;
        }

        return $map;
    }

    private function resolveThreshold(int $productId, int $branchId, array $map, int $globalThreshold): int
    {
        // Priority:
        // 1) product+branch
        // 2) product (all branches)
        // 3) branch default (all products)
        // 4) global
        return $map["p{$productId}-b{$branchId}"]
            ?? $map["p{$productId}-b0"]
            ?? $map["p0-b{$branchId}"]
            ?? $globalThreshold;
    }

    private function resolveCurrentTenantContext(): ?TenantContext
    {
        return app()->bound(TenantContext::class) ? app(TenantContext::class) : null;
    }
}
