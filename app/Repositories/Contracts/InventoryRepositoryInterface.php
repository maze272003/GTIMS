<?php

namespace App\Repositories\Contracts;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRepositoryInterface
{
    public function findById(int $id): ?Inventory;
    public function findByIdWithProduct(int $id): ?Inventory;
    public function getAllActive(): Collection;
    public function getByBranch(int $branchId): Collection;
    public function getByBranchPaginated(int $branchId, array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function getArchivedByProduct(int $productId): LengthAwarePaginator;
    public function findExistingStock(int $productId, string $batchNumber, string $expiryDate, int $branchId): ?Inventory;
    public function create(array $data): Inventory;
    public function update(Inventory $inventory, array $data): bool;
    public function delete(Inventory $inventory): bool;
    public function archiveByProduct(int $productId): void;
    public function unarchiveByProduct(int $productId): void;
    public function transferStock(Inventory $inventory, int $quantity, int $destinationBranchId): array;
}
