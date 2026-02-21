<?php

namespace App\Repositories\Eloquent;

use App\Models\Hold;
use App\Models\Inventory;
use App\Repositories\Interfaces\HoldRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class HoldRepository extends BaseRepository implements HoldRepositoryInterface
{
    public function __construct(Hold $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(
        ?string $status = null,
        ?string $type = null,
        ?int $branchId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->model
            ->with(['branch', 'creator', 'approver', 'items.product'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->latest()
            ->paginate($perPage);
    }

    public function findWithRelations(int $id): Hold
    {
        return $this->model->with([
            'branch',
            'barangay',
            'creator',
            'approver',
            'items.product',
            'items.inventory',
            'items.inventory.branch',
            'statusHistory.changer',
        ])->findOrFail($id);
    }

    public function getAvailableBatches(): Collection
    {
        return Inventory::query()
            ->where('quantity', '>', 0)
            ->withSum([
                'holdItems as held_quantity' => function ($query) {
                    $query->whereHas('hold', function ($holdQuery) {
                        $holdQuery->whereIn('status', ['pending', 'approved']);
                    });
                },
            ], 'quantity')
            ->orderBy('expiry_date')
            ->get(['id', 'product_id', 'batch_number', 'quantity'])
            ->map(function ($batch) {
                $available = max(0, (int) $batch->quantity - (int) ($batch->held_quantity ?? 0));
                $batch->available_quantity = $available;

                return $batch;
            })
            ->filter(function ($batch) {
                return (int) $batch->available_quantity > 0;
            })
            ->values();
    }
}
