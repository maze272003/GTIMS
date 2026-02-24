<?php

namespace App\Repositories\Interfaces;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SupplierRepositoryInterface extends RepositoryInterface
{
    /**
     * Get all suppliers with linked inventory count, paginated.
     */
    public function paginateWithProductCount(int $perPage = 20): LengthAwarePaginator;

    /**
     * Get a supplier with its linked inventory batches loaded.
     */
    public function findWithInventoryLinks(int $id): Supplier;

    /**
     * Link an inventory batch to a supplier.
     */
    public function linkInventory(int $supplierId, int $inventoryId, ?int $leadTimeDays = null, ?float $unitCost = null): void;

    /**
     * Unlink an inventory batch from a supplier.
     */
    public function unlinkInventory(int $supplierId, int $inventoryId): void;
}
