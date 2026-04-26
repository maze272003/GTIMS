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

class ProcessInventoryTransferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public int $sourceInventoryId,
        public int $destinationBranchId,
        public float $quantity,
        public ?int $userId = null,
    ) {
        $this->onQueue('inventory');
    }

    public function handle(InventoryAdminRepositoryInterface $repo): void
    {
        DB::transaction(function () use ($repo) {
            $source = Inventory::where('id', $this->sourceInventoryId)
                ->lockForUpdate()
                ->first();

            if (!$source) {
                Log::warning('ProcessInventoryTransferJob: source inventory not found', [
                    'source_inventory_id' => $this->sourceInventoryId,
                ]);
                return;
            }

            if ($source->quantity < $this->quantity) {
                Log::error('ProcessInventoryTransferJob: insufficient stock', [
                    'source_inventory_id' => $this->sourceInventoryId,
                    'available' => $source->quantity,
                    'requested' => $this->quantity,
                ]);
                $this->fail(new \RuntimeException('Insufficient stock for transfer.'));
                return;
            }

            $sourceQuantityBefore = $source->quantity;
            $source->quantity -= $this->quantity;
            $source->save();

            $dest = Inventory::where('product_id', $source->product_id)
                ->where('batch_number', $source->batch_number)
                ->where('branch_id', $this->destinationBranchId)
                ->where('expiry_date', $source->expiry_date)
                ->lockForUpdate()
                ->first();

            $destQuantityBefore = 0;

            if ($dest) {
                $destQuantityBefore = $dest->quantity;
                $dest->quantity += $this->quantity;
                $dest->save();
            } else {
                $dest = Inventory::create([
                    'product_id' => $source->product_id,
                    'batch_number' => $source->batch_number,
                    'quantity' => $this->quantity,
                    'expiry_date' => $source->expiry_date,
                    'branch_id' => $this->destinationBranchId,
                    'is_archived' => 0,
                ]);
            }

            $repo->createProductMovement([
                'product_id' => $source->product_id,
                'inventory_id' => $source->id,
                'user_id' => $this->userId,
                'type' => 'OUT',
                'quantity' => $this->quantity,
                'quantity_before' => $sourceQuantityBefore,
                'quantity_after' => $source->quantity,
                'description' => "Queued stock transfer to branch {$this->destinationBranchId}.",
            ]);

            $repo->createProductMovement([
                'product_id' => $source->product_id,
                'inventory_id' => $dest->id,
                'user_id' => $this->userId,
                'type' => 'IN',
                'quantity' => $this->quantity,
                'quantity_before' => $destQuantityBefore,
                'quantity_after' => $dest->quantity,
                'description' => "Queued stock received from branch {$source->branch_id}.",
            ]);
        });
    }

    public function tags(): array
    {
        return ['inventory', "transfer:{$this->sourceInventoryId}", "branch:{$this->destinationBranchId}"];
    }
}