<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderAdminService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public int $orderId,
        public string $status,
        public ?int $userId = null,
    ) {
        $this->onQueue('orders');
    }

    public function handle(OrderAdminService $orderService): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            Log::warning('ProcessOrderStatusJob: order not found', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        try {
            $orderService->updateStatus($order, $this->status, $this->userId);
        } catch (\Throwable $e) {
            Log::error('ProcessOrderStatusJob failed', [
                'order_id' => $this->orderId,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    public function tags(): array
    {
        return ['order', "order:{$this->orderId}"];
    }
}