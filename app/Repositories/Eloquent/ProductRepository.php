<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function getActive(): Collection
    {
        return $this->model
            ->where('is_archived', false)
            ->select(['id', 'generic_name', 'brand_name', 'form', 'strength', 'sku'])
            ->orderBy('generic_name')
            ->get();
    }

    public function paginateWithSearch(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->paginate($perPage);
    }
}
