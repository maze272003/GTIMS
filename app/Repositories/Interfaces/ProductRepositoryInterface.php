<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * Get all active (non-archived) products.
     */
    public function getActive(): Collection;

    /**
     * Paginate products with optional search filter.
     */
    public function paginateWithSearch(?string $search = null, int $perPage = 20): LengthAwarePaginator;
}
