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
            ->whereRaw('COALESCE(onhand_qty, quantity) > 0')
            ->whereHas('branch', fn($query) => $query->where('is_archived', false))
            ->withSum([
                'holdItems as held_quantity' => function ($query) {
                    $query->whereHas('hold', function ($holdQuery) {
                        $holdQuery->whereIn('status', ['pending', 'approved']);
                    });
                },
            ], 'quantity')
            ->orderBy('expiry_date')
            ->get(['id', 'product_id', 'batch_number', 'quantity', 'onhand_qty', 'hold_qty'])
            ->map(function ($batch) {
                $onHand = (int) ($batch->onhand_qty ?? $batch->quantity);
                $held = max((int) ($batch->hold_qty ?? 0), (int) ($batch->held_quantity ?? 0));
                $available = max(0, $onHand - $held);

                $batch->onhand_qty = $onHand;
                $batch->hold_qty = $held;
                $batch->available_quantity = $available;

                return $batch;
            })
            ->filter(function ($batch) {
                return (int) $batch->available_quantity > 0;
            })
            ->values();
    }
}
