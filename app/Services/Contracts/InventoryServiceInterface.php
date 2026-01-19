<?php

namespace App\Services\Contracts;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventoryServiceInterface
{
    public function getInventoryData(Request $request): array;
    public function getArchivedStocks(int $productId): LengthAwarePaginator;
    public function addProduct(array $validatedData): void;
    public function updateProduct(int $productId, array $validatedData): void;
    public function archiveProduct(int $productId): void;
    public function unarchiveProduct(int $productId): void;
    public function addStock(array $validatedData): void;
    public function editStock(int $inventoryId, array $validatedData): void;
    public function transferStock(int $inventoryId, int $quantity, int $destinationBranchId): void;
    public function validateStockAvailability(array $medications): void;
}
