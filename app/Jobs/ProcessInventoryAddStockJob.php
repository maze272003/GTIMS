<?php

namespace App\Jobs;

use App\Models\Inventory;
use App\Repositories\Interfaces\InventoryAdminRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessInventoryAddStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public int $inventoryId,
        public float $quantity,
        public ?int $userId = null,
    ) {
        $this->onQueue('inventory');
    }

    public function handle(InventoryAdminRepositoryInterface $repo): void
    {
        DB::transaction(function () use ($repo) {
            $inventory = Inventory::where('id', $this->inventoryId)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                Log::warning('ProcessInventoryAddStockJob: inventory not found', [
                    'inventory_id' => $this->inventoryId,
                ]);
                return;
            }

            $oldQuantity = $inventory->quantity;
            $inventory->quantity += $this->quantity;
            $inventory->save();

            $repo->createProductMovement([
                'product_id' => $inventory->product_id,
                'inventory_id' => $inventory->id,
                'user_id' => $this->userId,
                'type' => 'IN',
                'quantity' => $this->quantity,
                'quantity_before' => $oldQuantity,
                'quantity_after' => $inventory->quantity,
                'description' => 'Queued stock addition',
            ]);
        });
    }

    public function tags(): array
    {
        return ['inventory', "inventory:{$this->inventoryId}"];
    }
}