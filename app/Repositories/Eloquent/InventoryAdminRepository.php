<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Repositories\Interfaces\InventoryAdminRepositoryInterface;
use App\Support\SearchRelevance;
use Carbon\Carbon;
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

    public function getSupportedBranches(?array $branchIds = null): Collection
    {
        return Branch::query()
            ->active()
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('id', $branchIds))
            ->orderBy('name')
            ->get();
    }

    public function getActiveInventories(?array $branchIds = null): Collection
    {
        return Inventory::query()
            ->where('is_archived', '!=', 1)
            ->whereHas('branch', fn($query) => $query->where('is_archived', false))
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->get();
    }

    public function activeInventoryByBranchQuery(int $branchId): Builder
    {
        return Inventory::where('branch_id', $branchId)->where('is_archived', '!=', 1);
    }

    public function buildActiveInventoryByBranchQuery(int $branchId, ?string $search = null, ?string $filter = null): Builder
    {
        $normalizedSearch = SearchRelevance::normalize($search);
        $searchTokens = SearchRelevance::tokens($normalizedSearch);
        $query = Inventory::query()
            ->select('inventories.*')
            ->with('product')
            ->leftJoin('products', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.branch_id', $branchId)
            ->where('inventories.is_archived', '!=', 1);

        if ($normalizedSearch !== '') {
            $containsPattern = SearchRelevance::containsPattern($normalizedSearch);

            $query->where(function (Builder $searchQuery) use ($containsPattern, $searchTokens) {
                $searchQuery
                    ->whereRaw(SearchRelevance::lower('inventories.batch_number')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('products.generic_name')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('products.brand_name')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('products.form')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('products.strength')." LIKE ? ESCAPE '\\'", [$containsPattern]);

                if (count($searchTokens) > 1) {
                    $searchQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens) {
                        foreach ($searchTokens as $token) {
                            $tokenPattern = SearchRelevance::containsPattern($token);

                            $tokenQuery->where(function (Builder $fieldQuery) use ($tokenPattern) {
                                $fieldQuery
                                    ->whereRaw(SearchRelevance::lower('inventories.batch_number')." LIKE ? ESCAPE '\\'", [$tokenPattern])
                                    ->orWhereRaw(SearchRelevance::lower('products.generic_name')." LIKE ? ESCAPE '\\'", [$tokenPattern])
                                    ->orWhereRaw(SearchRelevance::lower('products.brand_name')." LIKE ? ESCAPE '\\'", [$tokenPattern]);
                            });
                        }
                    });
                }
            });

            $weights = config('query_relevance.inventory');
            $relevance = (new SearchRelevance())
                ->exact(SearchRelevance::lower('inventories.batch_number'), $normalizedSearch, $weights['batch_exact'])
                ->prefix(SearchRelevance::lower('inventories.batch_number'), $normalizedSearch, $weights['batch_prefix'])
                ->contains(SearchRelevance::lower('inventories.batch_number'), $normalizedSearch, $weights['batch_contains'])
                ->exact(SearchRelevance::lower('products.generic_name'), $normalizedSearch, $weights['generic_exact'])
                ->prefix(SearchRelevance::lower('products.generic_name'), $normalizedSearch, $weights['generic_prefix'])
                ->contains(SearchRelevance::lower('products.generic_name'), $normalizedSearch, $weights['generic_contains'])
                ->tokenContains(SearchRelevance::lower('products.generic_name'), $searchTokens, $weights['generic_token'])
                ->exact(SearchRelevance::lower('products.brand_name'), $normalizedSearch, $weights['brand_exact'])
                ->prefix(SearchRelevance::lower('products.brand_name'), $normalizedSearch, $weights['brand_prefix'])
                ->contains(SearchRelevance::lower('products.brand_name'), $normalizedSearch, $weights['brand_contains'])
                ->exact(SearchRelevance::lower('products.form'), $normalizedSearch, $weights['form_exact'])
                ->exact(SearchRelevance::lower('products.strength'), $normalizedSearch, $weights['strength_exact']);

            $compiled = $relevance->compile();
            $query->selectRaw($compiled['sql'], $compiled['bindings'])
                ->orderByDesc('relevance_score');
        }

        if ($filter) {
            match ($filter) {
                'in_stock' => $query->where('inventories.quantity', '>=', 100),
                'low_stock' => $query->where('inventories.quantity', '>', 0)->where('inventories.quantity', '<', 100),
                'out_of_stock' => $query->where('inventories.quantity', '<=', 0),
                'nearly_expired' => $query->where('inventories.expiry_date', '>', now())->where('inventories.expiry_date', '<', now()->addDays(30)),
                'expired' => $query->where('inventories.expiry_date', '<', now()),
                default => null,
            };
        }

        return $query
            ->orderByRaw('CASE WHEN inventories.expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('inventories.expiry_date', 'asc')
            ->orderByDesc('inventories.id');
    }

    public function getInventoryOverviewStats(?array $branchIds = null): array
    {
        $today = Carbon::today();
        $warningWindowEnd = Carbon::today()->addDays(30);

        $stats = Inventory::query()
            ->where('is_archived', '!=', 1)
            ->whereHas('branch', fn (Builder $query) => $query->where('is_archived', false))
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->selectRaw('SUM(CASE WHEN quantity >= 100 THEN 1 ELSE 0 END) as in_stock_count')
            ->selectRaw('SUM(CASE WHEN quantity > 0 AND quantity < 100 THEN 1 ELSE 0 END) as low_stock_count')
            ->selectRaw('SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END) as expired_count', [$today->toDateString()])
            ->selectRaw('SUM(CASE WHEN expiry_date > ? AND expiry_date < ? THEN 1 ELSE 0 END) as nearly_expired_count', [
                $today->toDateString(),
                $warningWindowEnd->toDateString(),
            ])
            ->first();

        return [
            'in_stock' => (int) ($stats?->in_stock_count ?? 0),
            'low_stock' => (int) ($stats?->low_stock_count ?? 0),
            'expired' => (int) ($stats?->expired_count ?? 0),
            'nearly_expired' => (int) ($stats?->nearly_expired_count ?? 0),
        ];
    }

    public function paginateArchivedStocksByProduct(int $productId, ?array $branchIds = null, int $perPage = 20): LengthAwarePaginator
    {
        return Inventory::where('is_archived', 1)
            ->where('product_id', $productId)
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
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
