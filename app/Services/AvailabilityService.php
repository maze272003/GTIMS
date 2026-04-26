<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\HoldItem;
use App\Models\ProductMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AvailabilityService
{
    /**
     * Get on-hand quantity for a product at a branch.
     */
    public function getOnHand(int $productId, ?int $branchId = null): int
    {
        $query = Inventory::where('product_id', $productId)
            ->where('is_archived', false);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return (int) ($query->selectRaw('COALESCE(SUM(COALESCE(onhand_qty, quantity)), 0) as aggregate')->value('aggregate') ?? 0);
    }

    /**
     * Get total held/reserved quantity for a product at a branch.
     */
    public function getHeldQuantity(int $productId, ?int $branchId = null): int
    {
        $inventoryQuery = Inventory::query()
            ->where('product_id', $productId)
            ->where('is_archived', false);

        if ($branchId) {
            $inventoryQuery->where('branch_id', $branchId);
        }

        $heldFromInventory = (int) ($inventoryQuery
            ->selectRaw('COALESCE(SUM(COALESCE(hold_qty, 0)), 0) as aggregate')
            ->value('aggregate') ?? 0);

        if ($heldFromInventory > 0) {
            return $heldFromInventory;
        }

        // Backward-compatible fallback for old rows/tests that only populated hold_items.
        $legacyHoldQuery = HoldItem::where('product_id', $productId)
            ->whereHas('hold', function ($q) use ($branchId) {
                $q->whereIn('status', ['pending', 'approved']);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            });

        return (int) $legacyHoldQuery->sum('quantity');
    }

    /**
     * Get available quantity (on-hand minus held).
     */
    public function getAvailable(int $productId, ?int $branchId = null): int
    {
        return max(0, $this->getOnHand($productId, $branchId) - $this->getHeldQuantity($productId, $branchId));
    }

    /**
     * Allocate batches using FEFO (First Expired First Out).
     * Returns array of allocations: [['inventory_id' => X, 'quantity' => Y], ...]
     */
    public function allocateFEFO(int $productId, int $quantity, ?int $branchId = null): array
    {
        $batches = Inventory::where('product_id', $productId)
            ->where('is_archived', false)
            ->whereRaw('COALESCE(onhand_qty, quantity) > 0')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('expiry_date', 'asc')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $available = max(0, (int) $batch->available_quantity);
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
                $beforeOnHand = (int) ($inventory->onhand_qty ?? $inventory->quantity);
                $heldQty = (int) ($inventory->hold_qty ?? 0);
                $available = max(0, $beforeOnHand - $heldQty);
                $deductQty = (int) $alloc['quantity'];

                if ($deductQty > $available) {
                    throw new RuntimeException("Insufficient available stock for inventory #{$inventory->id}. Requested {$deductQty}, available {$available}.");
                }

                $inventory->onhand_qty = $beforeOnHand - $deductQty;
                $inventory->save();

                ProductMovement::create([
                    'product_id' => $productId,
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'OUT',
                    'quantity' => $deductQty,
                    'quantity_before' => $beforeOnHand,
                    'quantity_after' => (int) $inventory->onhand_qty,
                    'description' => $description,
                ]);
            }
        });
    }
}
