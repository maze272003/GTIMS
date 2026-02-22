<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface HistoryLogRepositoryInterface extends RepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function getDistinctActions(): Collection;

    public function getDistinctUsers(): Collection;
}
