<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function findById(int $id): ?Inventory
    {
        return Inventory::find($id);
    }

    public function findByIdWithProduct(int $id): ?Inventory
    {
        return Inventory::with('product')->find($id);
    }

    public function getAllActive(): Collection
    {
        return Inventory::where('is_archived', '!=', 1)->get();
    }

    public function getByBranch(int $branchId): Collection
    {
        return Inventory::where('branch_id', $branchId)->where('is_archived', '!=', 1)->get();
    }

    public function getByBranchPaginated(int $branchId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Inventory::where('branch_id', $branchId)->where('is_archived', '!=', 1);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(batch_number) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('product', fn($p) => $p->whereRaw('LOWER(generic_name) LIKE ?', ["%{$search}%"])
                                               ->orWhereRaw('LOWER(brand_name) LIKE ?', ["%{$search}%"]));
            });
        }

        // Apply status filters
        if (!empty($filters['filter'])) {
            switch ($filters['filter']) {
                case 'in_stock':
                    $query->where('quantity', '>=', 100);
                    break;
                case 'low_stock':
                    $query->where('quantity', '>', 0)->where('quantity', '<', 100);
                    break;
                case 'out_of_stock':
                    $query->where('quantity', '<=', 0);
                    break;
                case 'nearly_expired':
                    $query->where('expiry_date', '>', now())->where('expiry_date', '<', now()->addDays(30));
                    break;
                case 'expired':
                    $query->where('expiry_date', '<', now());
                    break;
            }
        }

        return $query->with('product')
                    ->orderBy('expiry_date', 'asc')
                    ->paginate($perPage);
    }

    public function getArchivedByProduct(int $productId): LengthAwarePaginator
    {
        return Inventory::where('is_archived', 1)
                       ->where('product_id', $productId)
                       ->orderBy('expiry_date', 'desc')
                       ->paginate(20);
    }

    public function findExistingStock(int $productId, string $batchNumber, string $expiryDate, int $branchId): ?Inventory
    {
        return Inventory::where('product_id', $productId)
                       ->where('batch_number', $batchNumber)
                       ->where('expiry_date', $expiryDate)
                       ->where('branch_id', $branchId)
                       ->first();
    }

    public function create(array $data): Inventory
    {
        return Inventory::create($data);
    }

    public function update(Inventory $inventory, array $data): bool
    {
        return $inventory->update($data);
    }

    public function delete(Inventory $inventory): bool
    {
        return $inventory->delete();
    }

    public function archiveByProduct(int $productId): void
    {
        Inventory::where('product_id', $productId)->update(['is_archived' => 1]);
    }

    public function unarchiveByProduct(int $productId): void
    {
        Inventory::where('product_id', $productId)->update(['is_archived' => 0]);
    }

    public function transferStock(Inventory $inventory, int $quantity, int $destinationBranchId): array
    {
        // Check if destination batch exists
        $destInventory = Inventory::where('product_id', $inventory->product_id)
                                 ->where('batch_number', $inventory->batch_number)
                                 ->where('expiry_date', $inventory->expiry_date)
                                 ->where('branch_id', $destinationBranchId)
                                 ->first();

        $inventory->quantity -= $quantity;
        $inventory->save();

        if ($destInventory) {
            $oldQty = $destInventory->quantity;
            $destInventory->quantity += $quantity;
            $destInventory->save();
            $isNew = false;
        } else {
            // Create new batch for destination
            $destInventory = Inventory::create([
                'product_id'    => $inventory->product_id,
                'batch_number'  => $inventory->batch_number,
                'quantity'      => $quantity,
                'expiry_date'   => $inventory->expiry_date,
                'branch_id'     => $destinationBranchId,
                'is_archived'   => 2, // Transfer status
            ]);
            $oldQty = 0;
            $isNew = true;
        }

        return [
            'source_inventory' => $inventory,
            'destination_inventory' => $destInventory,
            'old_quantity' => $oldQty,
            'is_new' => $isNew
        ];
    }
}
