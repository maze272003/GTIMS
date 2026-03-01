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
        return Product::where('is_archived', 0)->orderBy('generic_name')->get();
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

    public function createOrderWithItems(int $requestingBranchId, int $sourceBranchId, int $userId, ?string $remarks, array $items): Order
    {
        return DB::transaction(function () use ($requestingBranchId, $sourceBranchId, $userId, $remarks, $items) {
            $order = Order::create([
                'branch_id' => $requestingBranchId,
                'user_id' => $userId,
                'status' => 'pending_admin',
                'remarks' => $remarks,
            ]);

            foreach ($items as $item) {
                $inventory = Inventory::query()
                    ->where('is_archived', false)
                    ->lockForUpdate()
                    ->find((int) $item['source_inventory_id']);

                if (!$inventory) {
                    throw new RuntimeException('Selected batch was not found.');
                }

                if ((int) $inventory->branch_id !== $sourceBranchId) {
                    throw new RuntimeException('Selected batch does not belong to the selected source branch.');
                }

                if ((int) $inventory->product_id !== (int) $item['product_id']) {
                    throw new RuntimeException('Selected batch does not match the chosen product.');
                }

                $requestedQty = (int) $item['quantity'];
                $beforeOnHand = (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0);
                $heldQty = (int) ($inventory->hold_qty ?? 0);
                $availableQty = max(0, $beforeOnHand - $heldQty);

                if ($requestedQty > $availableQty) {
                    throw new RuntimeException("Insufficient stock for batch {$inventory->batch_number}. Requested {$requestedQty}, available {$availableQty}.");
                }

                $inventory->onhand_qty = $beforeOnHand - $requestedQty;
                $inventory->save();

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $requestedQty,
                    'source_branch_id' => $sourceBranchId,
                    'source_inventory_id' => $inventory->id,
                    'source_batch_number' => $inventory->batch_number,
                ]);

                ProductMovement::create([
                    'product_id' => (int) $item['product_id'],
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'OUT',
                    'quantity' => $requestedQty,
                    'quantity_before' => $beforeOnHand,
                    'quantity_after' => (int) ($inventory->onhand_qty ?? 0),
                    'description' => "Order #{$order->id} submission",
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
}
