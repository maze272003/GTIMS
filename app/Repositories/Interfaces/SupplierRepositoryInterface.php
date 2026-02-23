<?php

namespace App\Repositories\Interfaces;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SupplierRepositoryInterface extends RepositoryInterface
{
    /**
     * Get all suppliers with product count, paginated.
     */
    public function paginateWithProductCount(int $perPage = 20): LengthAwarePaginator;

    /**
     * Get a supplier with its associated products loaded.
     */
    public function findWithProducts(int $id): Supplier;

    /**
     * Link a product to a supplier.
     */
    public function linkProduct(int $supplierId, int $productId, int $leadTimeDays, ?float $unitCost = null): void;

    /**
     * Unlink a product from a supplier.
     */
    public function unlinkProduct(int $supplierId, int $productId): void;
}
