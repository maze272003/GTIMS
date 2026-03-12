<?php

namespace App\Services;

use App\Models\IncomingRequest;
use App\Models\LowStockSetting;
use App\Models\ReorderRule;
use App\Models\Product;
use App\Models\Inventory;

class AnalyticsService
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    public function getRequestSLAMetrics(?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null, ?int $branchId = null): array
    {
        $query = IncomingRequest::query();
        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<=', $to);
        if ($branchId) $query->where('branch_id', $branchId);

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

    public function getReorderSuggestions(?int $branchId = null): array
    {
        $rules = ReorderRule::with(['product', 'preferredSupplier'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        $suggestions = [];

        foreach ($rules as $rule) {
            $available = $this->availabilityService->getAvailable(
                $rule->product_id,
                $rule->branch_id
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
     * Get low stock alerts across branches (or single branch if $branchId is provided).
     *
     * Returns array items with keys:
     * inventory_id, product_id, branch_id, batch_number, product_name,
     * branch_name, current_stock, threshold, on_hand, held
     */
    public function getLowStockAlerts(?int $branchId = null): array
    {
        $globalThreshold = (int) (LowStockSetting::where('is_global', true)->value('threshold') ?? 100);
        $rules = LowStockSetting::where('is_global', false)->get(['product_id', 'branch_id', 'threshold']);
        $ruleMap = $this->buildRuleMap($rules);

        $inventoryQuery = Inventory::query()
            ->where('is_archived', false)
            ->whereHas('product', fn($q) => $q->where('is_archived', false))
            ->whereHas('branch', fn($q) => $q->where('is_archived', false))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with([
                'product:id,generic_name,brand_name',
                'branch:id,name',
            ])
            ->withSum([
                'holdItems as held_quantity' => function ($q) {
                    $q->whereHas('hold', fn($h) => $h->whereIn('status', ['pending', 'approved']));
                },
            ], 'quantity');

        $batches = $inventoryQuery->get(['id', 'product_id', 'branch_id', 'batch_number', 'quantity']);

        $alerts = [];

        foreach ($batches as $batch) {
            $held = (int) ($batch->held_quantity ?? 0);
            $available = max(0, (int) $batch->quantity - $held);
            $thresholdData = $this->resolveThresholdWithSource(
                (int) $batch->product_id,
                (int) $batch->branch_id,
                $ruleMap,
                $globalThreshold
            );
            $threshold = (int) $thresholdData['threshold'];

            if ($available <= $threshold) {
                $alerts[] = [
                    'inventory_id'  => (int) $batch->id,
                    'product_id'    => (int) $batch->product_id,
                    'branch_id'     => (int) $batch->branch_id,
                    'batch_number'  => $batch->batch_number,
                    'product_name'  => $batch->product?->generic_name
                        ?? $batch->product?->brand_name
                        ?? ("Product #".$batch->product_id),
                    'branch_name'   => $batch->branch?->name ?? 'Unknown Branch',
                    'current_stock' => (int) $available,
                    'threshold'     => (int) $threshold,
                    'threshold_source' => (string) $thresholdData['source'],
                    'on_hand'       => (int) $batch->quantity,
                    'held'          => (int) $held,
                ];
            }
        }

        usort($alerts, fn($a, $b) => $a['current_stock'] <=> $b['current_stock']);

        return $alerts;
    }

    public function getStockKPIs(?int $branchId = null): array
    {
        $products = Product::where('is_archived', false)->get();

        $totalOnHand = 0;
        $totalHeld = 0;
        $totalAvailable = 0;

        foreach ($products as $product) {
            $onHand = $this->availabilityService->getOnHand($product->id, $branchId);
            $held = $this->availabilityService->getHeldQuantity($product->id, $branchId);
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
        return (int) $this->resolveThresholdWithSource($productId, $branchId, $map, $globalThreshold)['threshold'];
    }

    private function resolveThresholdWithSource(int $productId, int $branchId, array $map, int $globalThreshold): array
    {
        // Priority:
        // 1) product+branch
        // 2) product (all branches)
        // 3) branch default (all products)
        // 4) global
        $productBranchKey = "p{$productId}-b{$branchId}";
        if (array_key_exists($productBranchKey, $map)) {
            return [
                'threshold' => (int) $map[$productBranchKey],
                'source' => 'branch_override',
            ];
        }

        $productGlobalKey = "p{$productId}-b0";
        if (array_key_exists($productGlobalKey, $map)) {
            return [
                'threshold' => (int) $map[$productGlobalKey],
                'source' => 'global_override',
            ];
        }

        $branchDefaultKey = "p0-b{$branchId}";
        if (array_key_exists($branchDefaultKey, $map)) {
            return [
                'threshold' => (int) $map[$branchDefaultKey],
                'source' => 'branch_default',
            ];
        }

        return [
            'threshold' => (int) $globalThreshold,
            'source' => 'global_default',
        ];
    }
}
