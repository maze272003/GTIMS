<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['items.product', 'creator'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($perPage);
    }

    public function findWithItems(int $id): Order
    {
        return $this->model->with(['items.product', 'creator'])->findOrFail($id);
    }

    public function getPendingCount(string $status): int
    {
        return $this->model->where('status', $status)->count();
    }

    public function getGroupedActiveInventoryTotals(): Collection
    {
        return Inventory::where('is_archived', 0)
            ->select('product_id', 'branch_id', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_id', 'branch_id')
            ->get();
    }

    public function getActiveProductsOrdered(): Collection
    {
        return Product::where('is_archived', 0)->orderBy('generic_name')->get();
    }

    public function createOrderWithItems(int $branchId, int $userId, ?string $remarks, array $items): Order
    {
        return DB::transaction(function () use ($branchId, $userId, $remarks, $items) {
            $order = Order::create([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'status' => 'pending_admin',
                'remarks' => $remarks,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity'],
                ]);
            }

            return $order;
        });
    }

    public function paginateForUserBranch(int $branchId, bool $canSeeAll, int $perPage = 10): LengthAwarePaginator
    {
        $query = Order::with(['branch', 'user', 'items.product']);

        if (!$canSeeAll) {
            $query->where('branch_id', $branchId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findForPrint(int $id): Order
    {
        return Order::with(['items.product', 'branch', 'user'])->findOrFail($id);
    }
}
