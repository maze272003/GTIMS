<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductMovement;
use App\Repositories\Contracts\ProductMovementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductMovementRepository implements ProductMovementRepositoryInterface
{
    public function create(array $data): ProductMovement
    {
        return ProductMovement::create($data);
    }

    public function getByInventory(int $inventoryId): Collection
    {
        return ProductMovement::where('inventory_id', $inventoryId)
                             ->orderBy('created_at', 'desc')
                             ->get();
    }

    public function getByProduct(int $productId): Collection
    {
        return ProductMovement::where('product_id', $productId)
                             ->orderBy('created_at', 'desc')
                             ->get();
    }

    public function getRecentMovements(int $limit = 50): Collection
    {
        return ProductMovement::with(['product', 'inventory', 'user'])
                             ->orderBy('created_at', 'desc')
                             ->limit($limit)
                             ->get();
    }
}
