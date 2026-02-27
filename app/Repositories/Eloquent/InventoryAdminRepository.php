<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Repositories\Interfaces\InventoryAdminRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InventoryAdminRepository implements InventoryAdminRepositoryInterface
{
    public function getFocusInventoryWithProduct(int $inventoryId): ?Inventory
    {
        return Inventory::with('product')
            ->where('is_archived', '!=', 1)
            ->find($inventoryId);
    }

    public function getActiveProducts(): Collection
    {
        return Product::where('is_archived', 0)->get();
    }

    public function getArchivedProducts(): Collection
    {
        return Product::where('is_archived', 1)->get();
    }

    public function getSupportedBranches(): Collection
    {
        return Branch::query()->orderBy('name')->get();
    }

    public function getActiveInventories(): Collection
    {
        return Inventory::where('is_archived', '!=', 1)->get();
    }

    public function activeInventoryByBranchQuery(int $branchId): Builder
    {
        return Inventory::where('branch_id', $branchId)->where('is_archived', '!=', 1);
    }

    public function paginateArchivedStocksByProduct(int $productId, int $perPage = 20): LengthAwarePaginator
    {
        return Inventory::where('is_archived', 1)
            ->where('product_id', $productId)
            ->orderBy('expiry_date', 'desc')
            ->paginate($perPage);
    }

    public function createProduct(array $data): Product
    {
        return Product::create($data);
    }

    public function findProductOrFail(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function updateProduct(int $id, array $data): bool
    {
        return Product::findOrFail($id)->update($data);
    }

    public function updateStocksArchiveStateByProduct(int $productId, int $state): int
    {
        return Inventory::where('product_id', $productId)->update(['is_archived' => $state]);
    }

    public function findExistingStock(int $productId, string $batchNumber, string $expiryDate, int $branchId): ?Inventory
    {
        return Inventory::where('product_id', $productId)
            ->where('batch_number', $batchNumber)
            ->whereDate('expiry_date', $expiryDate)
            ->where('branch_id', $branchId)
            ->first();
    }

    public function createInventory(array $data): Inventory
    {
        return Inventory::create($data);
    }

    public function findInventoryWithProductOrFail(int $id): Inventory
    {
        return Inventory::with('product')->findOrFail($id);
    }

    public function createHistoryLog(array $data): void
    {
        HistoryLog::create($data);
    }

    public function createProductMovement(array $data): void
    {
        ProductMovement::create($data);
    }

    public function findBranchName(int $branchId): ?string
    {
        return Branch::find($branchId)?->name;
    }

    public function findTransferDestinationStock(Inventory $sourceInventory, int $destinationBranch): ?Inventory
    {
        return Inventory::where('product_id', $sourceInventory->product_id)
            ->where('batch_number', $sourceInventory->batch_number)
            ->where('expiry_date', $sourceInventory->expiry_date)
            ->where('branch_id', $destinationBranch)
            ->first();
    }
}
