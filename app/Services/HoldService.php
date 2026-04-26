<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Hold;
use App\Models\HoldItem;
use App\Models\HoldStatusHistory;
use App\Models\Inventory;
use App\Models\ProductMovement;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HoldService
{
    public function __construct(
        protected ?TransactionLogService $transactionLogService = null
    ) {
    }

    /**
     * Create a new hold with items and reserve stock via hold_qty (without reducing onhand_qty).
     */
    public function createHold(array $data, array $items, int $userId): Hold
    {
        try {
            return DB::transaction(function () use ($data, $items, $userId) {
            $branchId = (int) Arr::get($data, 'branch_id');
            $requestedByInventory = $this->groupRequestedByInventory($items);
            $inventoryIds = array_keys($requestedByInventory);
            $inventories = $this->lockInventories($inventoryIds, $branchId);

            $errors = [];
            foreach ($requestedByInventory as $inventoryId => $requestedQty) {
                $inventory = $inventories->get($inventoryId);
                if (!$inventory) {
                    $errors["items.{$inventoryId}.inventory_id"] = "Selected inventory #{$inventoryId} is invalid for this branch.";
                    continue;
                }

                $availableQty = $this->availableQty($inventory);
                if ($requestedQty > $availableQty) {
                    $errors["items.{$inventoryId}.quantity"] = "Requested hold quantity ({$requestedQty}) exceeds available quantity ({$availableQty}) for inventory #{$inventoryId}.";
                }
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $hold = Hold::create(array_merge($data, [
                'created_by' => $userId,
                'status' => 'pending',
            ]));

            $referenceNo = 'HOLD-'.$hold->id;

            foreach ($items as $item) {
                $inventoryId = (int) $item['inventory_id'];
                $holdQtyToAdd = (int) $item['quantity'];
                $inventory = $inventories->get($inventoryId);

                if (!$inventory) {
                    throw new RuntimeException("Inventory #{$inventoryId} is no longer available.");
                }

                $beforeHoldQty = (int) ($inventory->hold_qty ?? 0);
                $onHandQty = (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0);
                $inventory->hold_qty = $beforeHoldQty + $holdQtyToAdd;

                if ((int) $inventory->hold_qty > $onHandQty) {
                    throw ValidationException::withMessages([
                        "items.{$inventoryId}.quantity" => "Hold quantity cannot exceed on-hand quantity for inventory #{$inventoryId}.",
                    ]);
                }

                $inventory->save();

                $holdItem = HoldItem::create(array_merge($item, ['hold_id' => $hold->id]));
                $this->logHoldInventoryAudit(
                    action: 'hold.item.held',
                    inventory: $inventory,
                    userId: $userId,
                    reason: Arr::get($data, 'remarks'),
                    metadata: [
                        'hold_id' => $hold->id,
                        'hold_item_id' => $holdItem->id,
                        'reference_no' => $referenceNo,
                        'hold_qty_added' => $holdQtyToAdd,
                        'held_at' => now()->toIso8601String(),
                    ],
                    before: [
                        'onhand_qty' => $onHandQty,
                        'hold_qty' => $beforeHoldQty,
                        'available_qty' => max(0, $onHandQty - $beforeHoldQty),
                    ],
                    after: [
                        'onhand_qty' => (int) $inventory->onhand_qty,
                        'hold_qty' => (int) $inventory->hold_qty,
                        'available_qty' => $this->availableQty($inventory),
                    ]
                );

                $this->transactionLogService?->logStockHold('create', [
                    'hold_id' => $hold->id,
                    'hold_item_id' => $holdItem->id,
                    'inventory_id' => $inventory->id,
                    'product_id' => $holdItem->product_id,
                    'reference_no' => $referenceNo,
                    'held_at' => now()->toIso8601String(),
                    'held_by' => $userId,
                    'reason' => Arr::get($data, 'reason_code'),
                    'remarks' => Arr::get($data, 'remarks'),
                    'hold_qty_added' => $holdQtyToAdd,
                    'onhand_qty' => (int) $inventory->onhand_qty,
                    'hold_qty' => (int) $inventory->hold_qty,
                    'available_qty' => $this->availableQty($inventory),
                ]);
            }

            HoldStatusHistory::create([
                'hold_id' => $hold->id,
                'old_status' => null,
                'new_status' => 'pending',
                'changed_by' => $userId,
                'reason' => 'Hold created',
            ]);

            AuditEvent::create([
                'action' => 'hold.created',
                'entity_type' => 'hold',
                'entity_id' => $hold->id,
                'user_id' => $userId,
                'before' => null,
                'after' => array_merge($hold->toArray(), ['reference_no' => $referenceNo]),
                'reason' => Arr::get($data, 'remarks'),
                'metadata' => [
                    'reference_no' => $referenceNo,
                    'item_count' => count($items),
                ],
            ]);

            return $hold->fresh(['items']);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create hold', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Approve a hold.
     */
    public function approveHold(Hold $hold, int $approverId, ?string $reason = null): Hold
    {
        return DB::transaction(function () use ($hold, $approverId, $reason) {
            $hold = Hold::query()->lockForUpdate()->findOrFail($hold->id);
            $before = $hold->toArray();
            $oldStatus = $hold->status;

            $hold->update([
                'status' => 'approved',
                'approved_by' => $approverId,
            ]);

            HoldStatusHistory::create([
                'hold_id' => $hold->id,
                'old_status' => $oldStatus,
                'new_status' => 'approved',
                'changed_by' => $approverId,
                'reason' => $reason ?? 'Hold approved',
            ]);

            AuditEvent::create([
                'action' => 'hold.approved',
                'entity_type' => 'hold',
                'entity_id' => $hold->id,
                'user_id' => $approverId,
                'before' => $before,
                'after' => $hold->fresh()->toArray(),
                'reason' => $reason,
            ]);

            return $hold->fresh();
        });
    }

    /**
     * Release a hold and decrease hold_qty only.
     */
    public function releaseHold(Hold $hold, int $userId, ?string $reason = null): Hold
    {
        return $this->finalizeHold($hold, $userId, 'released', $reason ?? 'Hold released manually');
    }

    /**
     * Cancel a hold and decrease hold_qty only.
     */
    public function cancelHold(Hold $hold, int $userId, ?string $reason = null): Hold
    {
        return $this->finalizeHold($hold, $userId, 'cancelled', $reason ?? 'Hold cancelled');
    }

    /**
     * Pull out physical stock from inventory. By default, held stock cannot be pulled out.
     *
     * @throws ValidationException
     */
    public function pullOutInventory(
        int $inventoryId,
        int $quantity,
        int $userId,
        ?string $reason = null,
        ?string $referenceNo = null,
        bool $overrideHeld = false
    ): Inventory {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Pull-out quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($inventoryId, $quantity, $userId, $reason, $referenceNo, $overrideHeld) {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
            $onHandQty = (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0);
            $holdQty = (int) ($inventory->hold_qty ?? 0);
            $availableQty = max(0, $onHandQty - $holdQty);

            if ($quantity > $onHandQty) {
                throw ValidationException::withMessages([
                    'quantity' => "Pull-out quantity ({$quantity}) exceeds on-hand quantity ({$onHandQty}).",
                ]);
            }

            if (!$overrideHeld && $quantity > $availableQty) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot pull out held stock. Requested {$quantity}, available {$availableQty}, held {$holdQty}.",
                ]);
            }

            $effectiveReference = $referenceNo ?: 'PULLOUT-'.$inventory->id.'-'.now()->format('YmdHis');
            $before = [
                'onhand_qty' => $onHandQty,
                'hold_qty' => $holdQty,
                'available_qty' => $availableQty,
            ];

            $inventory->onhand_qty = $onHandQty - $quantity;
            if ($overrideHeld && $quantity > $availableQty) {
                $heldConsumed = $quantity - $availableQty;
                $inventory->hold_qty = max(0, $holdQty - $heldConsumed);
            }
            $inventory->save();

            ProductMovement::create([
                'product_id' => $inventory->product_id,
                'inventory_id' => $inventory->id,
                'user_id' => $userId,
                'type' => 'OUT',
                'quantity' => $quantity,
                'quantity_before' => $onHandQty,
                'quantity_after' => (int) $inventory->onhand_qty,
                'description' => "Pull-out ({$effectiveReference})".($reason ? " - {$reason}" : ''),
            ]);

            $after = [
                'onhand_qty' => (int) $inventory->onhand_qty,
                'hold_qty' => (int) $inventory->hold_qty,
                'available_qty' => $this->availableQty($inventory),
            ];

            $this->logHoldInventoryAudit(
                action: 'pullout.created',
                inventory: $inventory,
                userId: $userId,
                reason: $reason,
                metadata: [
                    'reference_no' => $effectiveReference,
                    'quantity' => $quantity,
                    'override_held' => $overrideHeld,
                    'pulled_at' => now()->toIso8601String(),
                ],
                before: $before,
                after: $after
            );

            $this->transactionLogService?->logPullOut([
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'reference_no' => $effectiveReference,
                'quantity' => $quantity,
                'override_held' => $overrideHeld,
                'reason' => $reason,
                'onhand_qty_before' => $onHandQty,
                'onhand_qty_after' => (int) $inventory->onhand_qty,
                'hold_qty_after' => (int) $inventory->hold_qty,
            ]);

            return $inventory->fresh();
        });
    }

    /**
     * Expire holds that have passed their expiry date. Called from scheduler.
     */
    public function expireHolds(): int
    {
        $expired = Hold::whereIn('status', ['pending', 'approved'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $hold) {
            try {
                $this->finalizeHold($hold, $hold->created_by, 'expired', 'Auto-expired');
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to expire hold', [
                    'hold_id' => $hold->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            Log::info('Holds expired by scheduler', ['count' => $count]);
        }

        return $count;
    }

    private function finalizeHold(Hold $hold, int $userId, string $newStatus, ?string $reason): Hold
    {
        return DB::transaction(function () use ($hold, $userId, $newStatus, $reason) {
            $hold = Hold::query()->lockForUpdate()->findOrFail($hold->id);
            $oldStatus = $hold->status;
            $before = $hold->toArray();

            if (!in_array($oldStatus, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'status' => "Hold can only be {$newStatus} from pending or approved state.",
                ]);
            }

            $hold->load('items');
            $inventoryIds = $hold->items->pluck('inventory_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
            $inventories = Inventory::query()->whereIn('id', $inventoryIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($hold->items as $item) {
                $inventory = $inventories->get((int) $item->inventory_id);
                if (!$inventory) {
                    throw new RuntimeException("Inventory #{$item->inventory_id} not found during hold {$newStatus}.");
                }

                $beforeHoldQty = (int) ($inventory->hold_qty ?? 0);
                $releaseQty = (int) $item->quantity;
                if ($releaseQty > $beforeHoldQty) {
                    throw new RuntimeException("Cannot {$newStatus} hold: release quantity ({$releaseQty}) exceeds held quantity ({$beforeHoldQty}) for inventory #{$inventory->id}.");
                }

                $inventory->hold_qty = $beforeHoldQty - $releaseQty;
                $inventory->save();

                $this->logHoldInventoryAudit(
                    action: "hold.item.{$newStatus}",
                    inventory: $inventory,
                    userId: $userId,
                    reason: $reason,
                    metadata: [
                        'hold_id' => $hold->id,
                        'hold_item_id' => $item->id,
                        'reference_no' => 'HOLD-'.$hold->id,
                        'released_qty' => $releaseQty,
                        'released_at' => now()->toIso8601String(),
                    ],
                    before: [
                        'onhand_qty' => (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0),
                        'hold_qty' => $beforeHoldQty,
                        'available_qty' => max(0, (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0) - $beforeHoldQty),
                    ],
                    after: [
                        'onhand_qty' => (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0),
                        'hold_qty' => (int) $inventory->hold_qty,
                        'available_qty' => $this->availableQty($inventory),
                    ]
                );

                $this->transactionLogService?->logStockHold($newStatus, [
                    'hold_id' => $hold->id,
                    'hold_item_id' => $item->id,
                    'inventory_id' => $inventory->id,
                    'product_id' => $item->product_id,
                    'reference_no' => 'HOLD-'.$hold->id,
                    'released_qty' => $releaseQty,
                    'reason' => $reason,
                    'onhand_qty' => (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0),
                    'hold_qty' => (int) $inventory->hold_qty,
                    'available_qty' => $this->availableQty($inventory),
                ]);
            }

            $hold->update(['status' => $newStatus]);

            HoldStatusHistory::create([
                'hold_id' => $hold->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $userId,
                'reason' => $reason,
            ]);

            AuditEvent::create([
                'action' => "hold.{$newStatus}",
                'entity_type' => 'hold',
                'entity_id' => $hold->id,
                'user_id' => $userId,
                'before' => $before,
                'after' => $hold->fresh()->toArray(),
                'reason' => $reason,
            ]);

            return $hold->fresh();
        });
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, int>
     */
    private function groupRequestedByInventory(array $items): array
    {
        $requested = [];
        foreach ($items as $item) {
            $inventoryId = (int) ($item['inventory_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($inventoryId <= 0 || $quantity <= 0) {
                continue;
            }
            $requested[$inventoryId] = ($requested[$inventoryId] ?? 0) + $quantity;
        }

        return $requested;
    }

    /**
     * @param array<int, int> $inventoryIds
     * @return Collection<int, Inventory>
     */
    private function lockInventories(array $inventoryIds, int $branchId): Collection
    {
        return Inventory::query()
            ->whereIn('id', $inventoryIds)
            ->where('branch_id', $branchId)
            ->where('is_archived', false)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function availableQty(Inventory $inventory): int
    {
        $onHand = (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0);
        $held = (int) ($inventory->hold_qty ?? 0);

        return max(0, $onHand - $held);
    }

    private function logHoldInventoryAudit(
        string $action,
        Inventory $inventory,
        int $userId,
        ?string $reason,
        array $metadata,
        array $before,
        array $after
    ): void {
        AuditEvent::create([
            'action' => $action,
            'entity_type' => 'inventory',
            'entity_id' => $inventory->id,
            'user_id' => $userId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
