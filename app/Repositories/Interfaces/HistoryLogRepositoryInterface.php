<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface HistoryLogRepositoryInterface extends RepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 20, ?int $branchId = null): LengthAwarePaginator;

    public function getDistinctActions(?int $branchId = null): Collection;

    public function getDistinctUsers(?int $branchId = null): Collection;
}
