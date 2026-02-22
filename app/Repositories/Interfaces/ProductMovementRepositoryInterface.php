<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductMovementRepositoryInterface extends RepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function getTodayStats(): array;
}

