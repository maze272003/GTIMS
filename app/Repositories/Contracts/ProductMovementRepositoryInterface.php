<?php

namespace App\Repositories\Contracts;

use App\Models\ProductMovement;
use Illuminate\Database\Eloquent\Collection;

interface ProductMovementRepositoryInterface
{
    public function create(array $data): ProductMovement;
    public function getByInventory(int $inventoryId): Collection;
    public function getByProduct(int $productId): Collection;
    public function getRecentMovements(int $limit = 50): Collection;
}
