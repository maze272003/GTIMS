<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
