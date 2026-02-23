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
        return $this->model->withCount('products')->paginate($perPage);
    }

    public function findWithProducts(int $id): Supplier
    {
        return $this->model->with('products')->findOrFail($id);
    }

    public function linkProduct(int $supplierId, int $productId, int $leadTimeDays, ?float $unitCost = null): void
    {
        SupplierProduct::updateOrCreate(
            ['supplier_id' => $supplierId, 'product_id' => $productId],
            ['lead_time_days' => $leadTimeDays, 'unit_cost' => $unitCost]
        );
    }

    public function unlinkProduct(int $supplierId, int $productId): void
    {
        SupplierProduct::where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->delete();
    }
}
