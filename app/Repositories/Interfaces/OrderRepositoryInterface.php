<?php

namespace App\Repositories\Interfaces;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
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

    public function getGroupedActiveInventoryTotals(): Collection;

    public function getActiveProductsOrdered(): Collection;

    public function getAvailableSourceInventoryByBranch(int $branchId): Collection;

    public function createOrderWithItems(int $requestingBranchId, int $userId, ?string $remarks, array $items): Order;

    public function paginateForUserBranch(int $branchId, bool $canSeeAll, int $perPage = 10): LengthAwarePaginator;

    public function findForPrint(int $id): Order;

    public function paginateApprovedForReceiving(int $branchId, bool $canSeeAll, int $perPage = 10): LengthAwarePaginator;

    public function findForReceiving(int $id): Order;

    public function receiveApprovedOrder(int $orderId, int $userId, array $items): Order;
}
