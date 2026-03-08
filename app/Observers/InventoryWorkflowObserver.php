<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\WorkflowTriggerService;
use Illuminate\Support\Facades\Log;

class InventoryWorkflowObserver
{
    public function __construct(
        protected WorkflowTriggerService $triggerService,
    ) {}

    /**
     * When stock is received (new batch added or quantity increased).
     */
    public function created(Inventory $inventory): void
    {
        try {
            $this->triggerService->fire('stock_received', [
                'product_id' => $inventory->product_id,
                'branch_id' => $inventory->branch_id,
                'batch_number' => $inventory->batch_number,
                'quantity' => $inventory->quantity,
                'available_qty' => $inventory->onhand_qty - $inventory->hold_qty,
                'expiry_date' => $inventory->expiry_date?->toDateString(),
            ], auth()->id());
        } catch (\Throwable $e) {
            Log::error('InventoryWorkflowObserver::created failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * When stock quantity changes, check for low stock.
     */
    public function updated(Inventory $inventory): void
    {
        // Only fire if quantity decreased
        if (!$inventory->wasChanged(['onhand_qty', 'quantity', 'hold_qty'])) {
            return;
        }

        $availableQty = $inventory->onhand_qty - $inventory->hold_qty;

        try {
            // Fire low stock trigger
            $this->triggerService->fire('low_stock_reached', [
                'product_id' => $inventory->product_id,
                'branch_id' => $inventory->branch_id,
                'batch_number' => $inventory->batch_number,
                'quantity' => $availableQty,
                'available_qty' => $availableQty,
                'expiry_date' => $inventory->expiry_date?->toDateString(),
            ], auth()->id());
        } catch (\Throwable $e) {
            Log::error('InventoryWorkflowObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }
}
