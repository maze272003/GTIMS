<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\WorkflowTriggerService;
use Illuminate\Support\Facades\Log;

class OrderWorkflowObserver
{
    public function __construct(
        protected WorkflowTriggerService $triggerService,
    ) {}

    /**
     * When a new order is created.
     */
    public function created(Order $order): void
    {
        try {
            $this->triggerService->fire('order_created', [
                'order_id' => $order->id,
                'branch_id' => $order->branch_id ?? null,
                'user_id' => $order->user_id ?? null,
                'status' => $order->status ?? 'pending',
            ], $order->user_id ?? null);
        } catch (\Throwable $e) {
            Log::error('OrderWorkflowObserver::created failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * When order status changes (approved/canceled).
     */
    public function updated(Order $order): void
    {
        if (!$order->wasChanged('status')) {
            return;
        }

        $newStatus = strtolower($order->status ?? '');

        try {
            if ($newStatus === 'approved') {
                $this->triggerService->fire('order_approved', [
                    'order_id' => $order->id,
                    'branch_id' => $order->branch_id ?? null,
                    'user_id' => $order->user_id ?? null,
                    'status' => $newStatus,
                ], $order->user_id ?? null);
            } elseif (in_array($newStatus, ['canceled', 'cancelled'])) {
                $this->triggerService->fire('order_canceled', [
                    'order_id' => $order->id,
                    'branch_id' => $order->branch_id ?? null,
                    'user_id' => $order->user_id ?? null,
                    'status' => $newStatus,
                ], $order->user_id ?? null);
            }
        } catch (\Throwable $e) {
            Log::error('OrderWorkflowObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }
}
