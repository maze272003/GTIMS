<?php

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Repositories\Interfaces\SupplierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }

    public function paginateWithProductCount(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->withCount(['supplierProducts as products_count'])
            ->paginate($perPage);
    }

    public function findWithInventoryLinks(int $id): Supplier
    {
        return $this->model
            ->with([
                'supplierProducts' => fn ($query) => $query
                    ->with(['inventory.product', 'inventory.branch'])
                    ->latest('id'),
            ])
            ->findOrFail($id);
    }

    public function linkInventory(int $supplierId, int $inventoryId, ?int $leadTimeDays = null, ?float $unitCost = null): void
    {
        SupplierProduct::updateOrCreate(
            ['supplier_id' => $supplierId, 'inventory_id' => $inventoryId],
            ['lead_time_days' => $leadTimeDays ?? 7, 'unit_cost' => $unitCost]
        );
    }

    public function unlinkInventory(int $supplierId, int $inventoryId): void
    {
        SupplierProduct::where('supplier_id', $supplierId)
            ->where('inventory_id', $inventoryId)
            ->delete();
    }
}
