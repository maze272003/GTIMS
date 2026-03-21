<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductMovement;
use App\Repositories\Interfaces\ProductMovementRepositoryInterface;
use App\Support\SearchRelevance;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductMovementRepository extends BaseRepository implements ProductMovementRepositoryInterface
{
    public function __construct(ProductMovement $model)
    {
        parent::__construct($model);
    }

    public function buildFilteredQuery(array $filters): Builder
    {
        $search = SearchRelevance::normalize($filters['search'] ?? '');
        $searchTokens = SearchRelevance::tokens($search);
        $productId = $filters['product_id'] ?? '';
        $type = $filters['type'] ?? '';
        $userId = $filters['user_id'] ?? '';
        $branchId = $filters['branch_id'] ?? '';
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        $sort = strtolower((string) ($filters['sort'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->model->newQuery()
            ->select('product_movements.*')
            ->with(['product', 'user', 'inventory.branch'])
            ->leftJoin('products', 'products.id', '=', 'product_movements.product_id')
            ->leftJoin('inventories', 'inventories.id', '=', 'product_movements.inventory_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventories.branch_id')
            ->leftJoin('users', 'users.id', '=', 'product_movements.user_id');

        if ($search !== '') {
            $containsPattern = SearchRelevance::containsPattern($search);

            $query->where(function (Builder $searchQuery) use ($search, $searchTokens, $containsPattern) {
                $searchQuery
                    ->whereRaw(SearchRelevance::lower('product_movements.description')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('inventories.batch_number')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('products.generic_name')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('products.brand_name')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('users.name')." LIKE ? ESCAPE '\\'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('branches.name')." LIKE ? ESCAPE '\\'", [$containsPattern]);

                if (in_array($search, ['in', 'out'], true)) {
                    $searchQuery->orWhereRaw(SearchRelevance::lower('product_movements.type').' = ?', [$search]);
                }

                if (count($searchTokens) > 1) {
                    $searchQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens) {
                        foreach ($searchTokens as $token) {
                            $tokenPattern = SearchRelevance::containsPattern($token);

                            $tokenQuery->where(function (Builder $fieldQuery) use ($tokenPattern) {
                                $fieldQuery
                                    ->whereRaw(SearchRelevance::lower('product_movements.description')." LIKE ? ESCAPE '\\'", [$tokenPattern])
                                    ->orWhereRaw(SearchRelevance::lower('products.generic_name')." LIKE ? ESCAPE '\\'", [$tokenPattern])
                                    ->orWhereRaw(SearchRelevance::lower('products.brand_name')." LIKE ? ESCAPE '\\'", [$tokenPattern])
                                    ->orWhereRaw(SearchRelevance::lower('inventories.batch_number')." LIKE ? ESCAPE '\\'", [$tokenPattern]);
                            });
                        }
                    });
                }
            });

            $weights = config('query_relevance.product_movements');
            $relevance = (new SearchRelevance())
                ->exact(SearchRelevance::lower('inventories.batch_number'), $search, $weights['batch_exact'])
                ->prefix(SearchRelevance::lower('inventories.batch_number'), $search, $weights['batch_prefix'])
                ->contains(SearchRelevance::lower('inventories.batch_number'), $search, $weights['batch_contains'])
                ->exact(SearchRelevance::lower('products.generic_name'), $search, $weights['product_exact'])
                ->prefix(SearchRelevance::lower('products.generic_name'), $search, $weights['product_prefix'])
                ->contains(SearchRelevance::lower('products.generic_name'), $search, $weights['product_contains'])
                ->exact(SearchRelevance::lower('products.brand_name'), $search, $weights['product_exact'])
                ->prefix(SearchRelevance::lower('products.brand_name'), $search, $weights['product_prefix'])
                ->contains(SearchRelevance::lower('products.brand_name'), $search, $weights['product_contains'])
                ->prefix(SearchRelevance::lower('product_movements.description'), $search, $weights['description_prefix'])
                ->contains(SearchRelevance::lower('product_movements.description'), $search, $weights['description_contains'])
                ->tokenContains(SearchRelevance::lower('product_movements.description'), $searchTokens, $weights['description_token'])
                ->exact(SearchRelevance::lower('users.name'), $search, $weights['user_exact'])
                ->exact(SearchRelevance::lower('branches.name'), $search, $weights['branch_exact']);

            if (in_array($search, ['in', 'out'], true)) {
                $relevance->exact(SearchRelevance::lower('product_movements.type'), $search, $weights['type_exact']);
            }

            $compiled = $relevance->compile();
            $query->selectRaw($compiled['sql'], $compiled['bindings'])
                ->orderByDesc('relevance_score');
        }

        if ($productId !== '') {
            $query->where('product_movements.product_id', $productId);
        }

        if ($type !== '') {
            $query->where('product_movements.type', $type);
        }

        if ($userId !== '') {
            $query->where('product_movements.user_id', $userId);
        }

        if ($branchId !== '') {
            $query->where('inventories.branch_id', $branchId);
        }

        if ($from !== '' && $to !== '') {
            $query->whereBetween('product_movements.created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        } elseif ($from !== '') {
            $query->where('product_movements.created_at', '>=', Carbon::parse($from)->startOfDay());
        } elseif ($to !== '') {
            $query->where('product_movements.created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query->orderBy('product_movements.created_at', $sort);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->buildFilteredQuery($filters)->paginate($perPage);
    }

    public function getTodayStats(?int $branchId = null): array
    {
        $today = Carbon::today();
        $stats = $this->model->newQuery()
            ->join('inventories', 'inventories.id', '=', 'product_movements.inventory_id')
            ->when($branchId, fn (Builder $query) => $query->where('inventories.branch_id', $branchId))
            ->whereDate('product_movements.created_at', $today)
            ->selectRaw('COUNT(*) as movements_today_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN product_movements.type = 'IN' THEN product_movements.quantity ELSE 0 END), 0) as items_in_today")
            ->selectRaw("COALESCE(SUM(CASE WHEN product_movements.type = 'OUT' THEN product_movements.quantity ELSE 0 END), 0) as items_out_today")
            ->first();

        return [
            'movementsTodayCount' => (int) ($stats?->movements_today_count ?? 0),
            'itemsInToday' => (int) ($stats?->items_in_today ?? 0),
            'itemsOutToday' => (int) ($stats?->items_out_today ?? 0),
        ];
    }
}
