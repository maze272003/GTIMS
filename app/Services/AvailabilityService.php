<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\HoldItem;
use App\Models\ProductMovement;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /**
     * Get on-hand quantity for a product, optionally scoped by tenant or branch.
     */
    public function getOnHand(int $productId, ?int $branchId = null, ?TenantContext $tenantContext = null): int
    {
        $tenantContext = $tenantContext ?: (app()->bound(TenantContext::class) ? app(TenantContext::class) : null);

        $query = TenantScope::apply(
            Inventory::query()->where('product_id', $productId)->where('is_archived', false),
            $tenantContext
        );

        if ((!$tenantContext || $tenantContext->isPlatform()) && $branchId) {
            $query->where('branch_id', $branchId);
        }

        return (int) $query->sum('quantity');
    }

    /**
     * Get total held/reserved quantity for a product, optionally scoped by tenant or branch.
     */
    public function getHeldQuantity(int $productId, ?int $branchId = null, ?TenantContext $tenantContext = null): int
    {
        $tenantContext = $tenantContext ?: (app()->bound(TenantContext::class) ? app(TenantContext::class) : null);

        $query = HoldItem::where('product_id', $productId)
            ->whereHas('hold', function ($q) use ($branchId, $tenantContext) {
                $q->whereIn('status', ['pending', 'approved']);

                if ($tenantContext && !$tenantContext->isPlatform()) {
                    TenantScope::apply($q, $tenantContext, 'holds');
                } elseif ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            });

        return (int) $query->sum('quantity');
    }

    /**
     * Get available quantity (on-hand minus held).
     */
    public function getAvailable(int $productId, ?int $branchId = null, ?TenantContext $tenantContext = null): int
    {
        return max(0, $this->getOnHand($productId, $branchId, $tenantContext) - $this->getHeldQuantity($productId, $branchId, $tenantContext));
    }

    /**
     * Allocate batches using FEFO (First Expired First Out).
     * Returns array of allocations: [['inventory_id' => X, 'quantity' => Y], ...]
     */
    public function allocateFEFO(int $productId, int $quantity, ?int $branchId = null, ?TenantContext $tenantContext = null): array
    {
        $tenantContext = $tenantContext ?: (app()->bound(TenantContext::class) ? app(TenantContext::class) : null);

        $query = Inventory::query()->where('product_id', $productId)
            ->where('is_archived', false)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->lockForUpdate();

        TenantScope::apply($query, $tenantContext);

        if ((!$tenantContext || $tenantContext->isPlatform()) && $branchId) {
            $query->where('branch_id', $branchId);
        }

        $batches = $query->get();

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $available = $batch->available_quantity;
            if ($available <= 0) continue;

            $allocateQty = min($remaining, $available);
            $allocations[] = [
                'inventory_id' => $batch->id,
                'quantity' => $allocateQty,
            ];
            $remaining -= $allocateQty;
        }

        return $allocations;
    }

    /**
     * Execute a stock deduction with movement logging inside a transaction.
     */
    public function deductStock(array $allocations, int $productId, int $userId, string $description): void
    {
        DB::transaction(function () use ($allocations, $productId, $userId, $description) {
            foreach ($allocations as $alloc) {
                $inventory = Inventory::lockForUpdate()->findOrFail($alloc['inventory_id']);
                $before = $inventory->quantity;
                $inventory->quantity -= $alloc['quantity'];
                $inventory->save();

                ProductMovement::create([
                    'product_id' => $productId,
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'OUT',
                    'quantity' => $alloc['quantity'],
                    'quantity_before' => $before,
                    'quantity_after' => $inventory->quantity,
                    'description' => $description,
                ]);
            }
        });
    }
}
