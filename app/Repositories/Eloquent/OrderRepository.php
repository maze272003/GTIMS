<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['items.product', 'items.sourceBranch', 'user'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($perPage);
    }

    public function findWithItems(int $id): Order
    {
        return $this->model->with(['items.product', 'items.sourceBranch', 'user'])->findOrFail($id);
    }

    public function getPendingCount(string $status): int
    {
        return $this->model->where('status', $status)->count();
    }

    public function getGroupedActiveInventoryTotals(): Collection
    {
        $availableExpr = '(COALESCE(onhand_qty, quantity) - COALESCE(hold_qty, 0))';

        return Inventory::where('is_archived', 0)
            ->whereHas('branch', fn($q) => $q->where('is_archived', false))
            ->select('product_id', 'branch_id', DB::raw("SUM(CASE WHEN {$availableExpr} > 0 THEN {$availableExpr} ELSE 0 END) as total_qty"))
            ->groupBy('product_id', 'branch_id')
            ->get();
    }

    public function getActiveProductsOrdered(): Collection
    {
        return Product::where('is_archived', 0)
            ->select(['id', 'generic_name', 'brand_name', 'form', 'strength'])
            ->orderBy('generic_name')
            ->get();
    }

    public function getAvailableSourceInventoryByBranch(int $branchId): Collection
    {
        $availableExpr = '(COALESCE(onhand_qty, quantity) - COALESCE(hold_qty, 0))';

        return Inventory::query()
            ->with(['product:id,generic_name,brand_name'])
            ->where('is_archived', false)
            ->where('branch_id', $branchId)
            ->whereHas('branch', fn ($q) => $q->where('is_archived', false))
            ->whereHas('product', fn ($q) => $q->where('is_archived', 0))
            ->whereRaw("{$availableExpr} > 0")
            ->select([
                'id',
                'product_id',
                'branch_id',
                'batch_number',
                'expiry_date',
                'created_at',
                DB::raw("{$availableExpr} as available_qty"),
            ])
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiry_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function createOrderWithItems(int $requestingBranchId, int $userId, ?string $remarks, array $items): Order
    {
        return DB::transaction(function () use ($requestingBranchId, $userId, $remarks, $items) {
            $order = Order::create([
                'branch_id' => $requestingBranchId,
                'user_id' => $userId,
                'status' => 'pending_admin',
                'remarks' => $remarks,
            ]);

            foreach ($items as $item) {
                $requestedQty = (int) $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $requestedQty,
                ]);
            }

            return $order;
        });
    }

    public function paginateForUserBranch(int $branchId, bool $canSeeAll, int $perPage = 10): LengthAwarePaginator
    {
        $query = Order::with(['branch', 'user', 'items.product', 'items.sourceBranch']);

        if (!$canSeeAll) {
            $query->where('branch_id', $branchId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findForPrint(int $id): Order
    {
        return Order::with(['items.product', 'items.sourceBranch', 'branch', 'user'])->findOrFail($id);
    }

    public function paginateApprovedForReceiving(int $branchId, bool $canSeeAll, int $perPage = 10): LengthAwarePaginator
    {
        $query = Order::with(['branch', 'user', 'receiver', 'items.product'])
            ->where('status', 'approved');

        if (!$canSeeAll) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->orderByRaw('CASE WHEN received_at IS NULL THEN 0 ELSE 1 END ASC')
            ->latest()
            ->paginate($perPage);
    }

    public function findForReceiving(int $id): Order
    {
        return Order::with(['branch', 'user', 'receiver', 'items.product'])->findOrFail($id);
    }

    public function receiveApprovedOrder(int $orderId, int $userId, array $items): Order
    {
        return DB::transaction(function () use ($orderId, $userId, $items) {
            $order = Order::with(['items.product', 'branch', 'user'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status !== 'approved') {
                throw new RuntimeException('Only approved orders can be received.');
            }

            if ($order->received_at) {
                throw new RuntimeException('This order has already been received.');
            }

            $itemsById = collect($items)->keyBy(fn ($value, $key) => (int) $key);

            foreach ($order->items as $orderItem) {
                $receivedItem = $itemsById->get((int) $orderItem->id);
                $batches = is_array($receivedItem['batches'] ?? null) ? $receivedItem['batches'] : [];

                if ($batches === []) {
                    throw new RuntimeException("No batches were provided for {$orderItem->product?->generic_name}.");
                }

                $receivedTotal = 0;

                foreach ($batches as $batch) {
                    $batchNumber = trim((string) ($batch['batch_number'] ?? ''));
                    $quantity = (int) ($batch['quantity'] ?? 0);
                    $expiryDate = $batch['expiry_date'] ?? null;

                    if ($batchNumber === '') {
                        throw new RuntimeException("Batch number is required for {$orderItem->product?->generic_name}.");
                    }

                    if ($quantity <= 0) {
                        throw new RuntimeException("Received quantity must be greater than zero for {$orderItem->product?->generic_name}.");
                    }

                    if (!$expiryDate) {
                        throw new RuntimeException("Expiry date is required for batch {$batchNumber}.");
                    }

                    $receivedTotal += $quantity;

                    $inventory = Inventory::query()
                        ->where('branch_id', $order->branch_id)
                        ->where('product_id', $orderItem->product_id)
                        ->where('batch_number', $batchNumber)
                        ->whereDate('expiry_date', $expiryDate)
                        ->where('is_archived', false)
                        ->lockForUpdate()
                        ->first();

                    $beforeQuantity = (int) ($inventory?->quantity ?? 0);

                    if ($inventory) {
                        $inventory->quantity = $beforeQuantity + $quantity;
                        $inventory->save();
                    } else {
                        $inventory = Inventory::create([
                            'branch_id' => $order->branch_id,
                            'product_id' => $orderItem->product_id,
                            'batch_number' => $batchNumber,
                            'expiry_date' => $expiryDate,
                            'quantity' => $quantity,
                            'hold_qty' => 0,
                            'is_archived' => false,
                        ]);
                    }

                    ProductMovement::create([
                        'product_id' => $orderItem->product_id,
                        'inventory_id' => $inventory->id,
                        'user_id' => $userId,
                        'type' => 'IN',
                        'quantity' => $quantity,
                        'quantity_before' => $beforeQuantity,
                        'quantity_after' => (int) $inventory->quantity,
                        'description' => "Order #{$order->id} received into inventory",
                    ]);
                }

                if ($receivedTotal !== (int) $orderItem->quantity_requested) {
                    throw new RuntimeException(
                        "{$orderItem->product?->generic_name} must receive exactly {$orderItem->quantity_requested} units. Received {$receivedTotal}."
                    );
                }
            }

            $order->update([
                'received_at' => now(),
                'received_by' => $userId,
            ]);

            return $order->fresh(['branch', 'user', 'receiver', 'items.product']);
        });
    }
}
