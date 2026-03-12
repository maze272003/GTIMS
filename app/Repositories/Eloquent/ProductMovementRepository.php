<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductMovement;
use App\Repositories\Interfaces\ProductMovementRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductMovementRepository extends BaseRepository implements ProductMovementRepositoryInterface
{
    public function __construct(ProductMovement $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $productId = $filters['product_id'] ?? '';
        $type = $filters['type'] ?? '';
        $userId = $filters['user_id'] ?? '';
        $branchId = $filters['branch_id'] ?? '';
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        $sort = strtolower((string) ($filters['sort'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->model->newQuery()
            ->with(['product', 'user', 'inventory.branch'])
            ->orderBy('created_at', $sort);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('inventory', fn ($qInv) => $qInv->where('batch_number', 'like', "%{$search}%"));
            });
        }

        if ($productId !== '') {
            $query->where('product_id', $productId);
        }
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($userId !== '') {
            $query->where('user_id', $userId);
        }
        if ($branchId !== '') {
            $query->whereHas('inventory', fn ($q) => $q->where('branch_id', $branchId));
        }

        if ($from !== '' && $to !== '') {
            $query->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        } elseif ($from !== '') {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        } elseif ($to !== '') {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query->paginate($perPage);
    }

    public function getTodayStats(?int $branchId = null): array
    {
        $today = Carbon::today();

        return [
            'movementsTodayCount' => $this->model->newQuery()
                ->when($branchId, fn ($query) => $query->whereHas('inventory', fn ($inventoryQuery) => $inventoryQuery->where('branch_id', $branchId)))
                ->whereDate('created_at', $today)
                ->count(),
            'itemsInToday' => $this->model->newQuery()
                ->when($branchId, fn ($query) => $query->whereHas('inventory', fn ($inventoryQuery) => $inventoryQuery->where('branch_id', $branchId)))
                ->where('type', 'IN')
                ->whereDate('created_at', $today)
                ->sum('quantity'),
            'itemsOutToday' => $this->model->newQuery()
                ->when($branchId, fn ($query) => $query->whereHas('inventory', fn ($inventoryQuery) => $inventoryQuery->where('branch_id', $branchId)))
                ->where('type', 'OUT')
                ->whereDate('created_at', $today)
                ->sum('quantity'),
        ];
    }
}
