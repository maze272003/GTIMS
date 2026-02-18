<?php

namespace App\Services;

use App\Models\IncomingRequest;
use App\Models\RequestStatusHistory;
use App\Models\LowStockSetting;
use App\Models\ReorderRule;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Get SLA metrics for requests.
     */
    public function getRequestSLAMetrics(?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null): array
    {
        $query = IncomingRequest::query();
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

    /**
     * Get reorder suggestions based on rules and current stock.
     */
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
                        ? $rule->product->suppliers()
                            ->where('supplier_id', $rule->preferred_supplier_id)
                            ->first()?->pivot?->lead_time_days
                        : null,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get low stock alerts based on settings.
     */
    public function getLowStockAlerts(?int $branchId = null): array
    {
        $products = Product::where('is_archived', false)->get();
        $alerts = [];

        foreach ($products as $product) {
            $threshold = LowStockSetting::getThresholdFor($product->id, $branchId);
            $available = $this->availabilityService->getAvailable($product->id, $branchId);

            if ($available <= $threshold) {
                $alerts[] = [
                    'product' => $product,
                    'available' => $available,
                    'threshold' => $threshold,
                    'branch_id' => $branchId,
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get available stock KPIs for dashboard.
     */
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
}
