<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface ProductMovementRepositoryInterface extends RepositoryInterface
{
    public function buildFilteredQuery(array $filters): Builder;

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function getTodayStats(?int $branchId = null): array;
}
