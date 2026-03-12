<?php

namespace App\Repositories\Interfaces;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface InventoryAdminRepositoryInterface
{
    public function getFocusInventoryWithProduct(int $inventoryId): ?Inventory;

    public function getActiveProducts(): Collection;

    public function getArchivedProducts(): Collection;

    public function getSupportedBranches(?array $branchIds = null): Collection;

    public function getActiveInventories(?array $branchIds = null): Collection;

    public function activeInventoryByBranchQuery(int $branchId): Builder;

    public function paginateArchivedStocksByProduct(int $productId, ?array $branchIds = null, int $perPage = 20): LengthAwarePaginator;

    public function createProduct(array $data): Product;

    public function findProductOrFail(int $id): Product;

    public function updateProduct(int $id, array $data): bool;

    public function updateStocksArchiveStateByProduct(int $productId, int $state): int;

    public function findExistingStock(int $productId, string $batchNumber, string $expiryDate, int $branchId): ?Inventory;

    public function createInventory(array $data): Inventory;

    public function findInventoryWithProductOrFail(int $id): Inventory;

    public function createHistoryLog(array $data): void;

    public function createProductMovement(array $data): void;

    public function findBranchName(int $branchId): ?string;

    public function findTransferDestinationStock(Inventory $sourceInventory, int $destinationBranch): ?Inventory;
}
