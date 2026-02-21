<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate orders with eager loading and optional filters.
     */
    public function paginateWithFilters(?string $status = null, int $perPage = 20): LengthAwarePaginator;

    /**
     * Find an order with items and related products.
     */
    public function findWithItems(int $id): \App\Models\Order;

    /**
     * Get pending order count for a specific status.
     */
    public function getPendingCount(string $status): int;
}
