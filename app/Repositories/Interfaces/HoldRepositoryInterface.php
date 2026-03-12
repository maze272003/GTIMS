<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface HoldRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate holds with filtering and eager loading.
     */
    public function paginateWithFilters(
        ?string $status = null,
        ?string $type = null,
        ?int $branchId = null,
        int $perPage = 20
    ): LengthAwarePaginator;

    /**
     * Find a hold with all its relations loaded.
     */
    public function findWithRelations(int $id): \App\Models\Hold;

    /**
     * Get available inventory batches with held quantity calculations.
     */
    public function getAvailableBatches(?array $branchIds = null): Collection;
}
