<?php

namespace App\Repositories\Eloquent;

use App\Models\HistoryLog;
use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use App\Support\SearchRelevance;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class HistoryLogRepository extends BaseRepository implements HistoryLogRepositoryInterface
{
    public function __construct(HistoryLog $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20, ?int $branchId = null): LengthAwarePaginator
    {
        $search = SearchRelevance::normalize($filters['search'] ?? '');
        $searchTokens = SearchRelevance::tokens($search);
        $action = (string) ($filters['action'] ?? '');
        $user = (string) ($filters['user'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        $sort = strtolower((string) ($filters['sort'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->model->newQuery()
            ->select('history_logs.*');

        if ($branchId) {
            $query->leftJoin('users', 'users.id', '=', 'history_logs.user_id');
        }

        $this->applyBranchScope($query, $branchId);

        if ($search !== '') {
            $containsPattern = SearchRelevance::containsPattern($search);

            $query->where(function (Builder $searchQuery) use ($search, $searchTokens, $containsPattern) {
                $searchQuery
                    ->whereRaw(SearchRelevance::lower('history_logs.action')." LIKE ? ESCAPE '!'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('history_logs.description')." LIKE ? ESCAPE '!'", [$containsPattern])
                    ->orWhereRaw(SearchRelevance::lower('history_logs.user_name')." LIKE ? ESCAPE '!'", [$containsPattern]);

                if (count($searchTokens) > 1) {
                    $searchQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens) {
                        foreach ($searchTokens as $token) {
                            $tokenPattern = SearchRelevance::containsPattern($token);

                            $tokenQuery->where(function (Builder $fieldQuery) use ($tokenPattern) {
                                $fieldQuery
                                    ->whereRaw(SearchRelevance::lower('history_logs.description')." LIKE ? ESCAPE '!'", [$tokenPattern])
                                    ->orWhereRaw(SearchRelevance::lower('history_logs.user_name')." LIKE ? ESCAPE '!'", [$tokenPattern]);
                            });
                        }
                    });
                }

                $this->applyCreatedAtSearch($searchQuery, $search);
            });

            $weights = config('query_relevance.history_logs');
            $relevance = (new SearchRelevance())
                ->exact(SearchRelevance::lower('history_logs.action'), $search, $weights['action_exact'])
                ->prefix(SearchRelevance::lower('history_logs.action'), $search, $weights['action_prefix'])
                ->contains(SearchRelevance::lower('history_logs.action'), $search, $weights['action_contains'])
                ->exact(SearchRelevance::lower('history_logs.user_name'), $search, $weights['user_exact'])
                ->prefix(SearchRelevance::lower('history_logs.user_name'), $search, $weights['user_prefix'])
                ->contains(SearchRelevance::lower('history_logs.user_name'), $search, $weights['user_contains'])
                ->prefix(SearchRelevance::lower('history_logs.description'), $search, $weights['description_prefix'])
                ->contains(SearchRelevance::lower('history_logs.description'), $search, $weights['description_contains'])
                ->tokenContains(SearchRelevance::lower('history_logs.description'), $searchTokens, $weights['description_token']);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) === 1) {
                $relevance->custom('DATE(history_logs.created_at) = ?', [$search], $weights['date_exact']);
            }

            $compiled = $relevance->compile();
            $query->selectRaw($compiled['sql'], $compiled['bindings'])
                ->orderByDesc('relevance_score');
        }

        if ($action !== '') {
            $query->where('history_logs.action', $action);
        }

        if ($user !== '') {
            $query->where('history_logs.user_name', $user);
        }

        if ($from !== '' && $to !== '') {
            $query->whereBetween('history_logs.created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        } elseif ($from !== '') {
            $query->where('history_logs.created_at', '>=', Carbon::parse($from)->startOfDay());
        } elseif ($to !== '') {
            $query->where('history_logs.created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query->orderBy('history_logs.created_at', $sort)->paginate($perPage);
    }

    public function getDistinctActions(?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()->select('history_logs.action')->distinct();

        if ($branchId) {
            $query->leftJoin('users', 'users.id', '=', 'history_logs.user_id');
        }

        $this->applyBranchScope($query, $branchId);

        return $query->whereNotNull('history_logs.action')
            ->orderBy('history_logs.action')
            ->pluck('history_logs.action');
    }

    public function getDistinctUsers(?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()->select('history_logs.user_name')->distinct();

        if ($branchId) {
            $query->leftJoin('users', 'users.id', '=', 'history_logs.user_id');
        }

        $this->applyBranchScope($query, $branchId);

        return $query->whereNotNull('history_logs.user_name')
            ->orderBy('history_logs.user_name')
            ->pluck('history_logs.user_name');
    }

    private function applyBranchScope(Builder $query, ?int $branchId): void
    {
        if (!$branchId) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        $query->where(function (Builder $branchQuery) use ($branchId, $driver) {
            $branchQuery->where('users.branch_id', $branchId);

            if ($driver === 'sqlite') {
                $branchQuery->orWhereRaw("json_extract(history_logs.metadata, '$.branch_id') = ?", [$branchId]);
                $branchQuery->orWhere(function (Builder $legacyQuery) {
                    $legacyQuery
                        ->whereNull('users.branch_id')
                        ->whereRaw("json_extract(history_logs.metadata, '$.branch_id') IS NULL");
                });
                return;
            }

            $branchQuery->orWhere('history_logs.metadata->branch_id', $branchId);
            $branchQuery->orWhere(function (Builder $legacyQuery) {
                $legacyQuery
                    ->whereNull('users.branch_id')
                    ->where(function (Builder $metadataQuery) {
                        $metadataQuery
                            ->whereNull('history_logs.metadata')
                            ->orWhereNull('history_logs.metadata->branch_id');
                    });
            });
        });
    }

    private function applyCreatedAtSearch(Builder $query, string $search): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $query->orWhereRaw("DATE_FORMAT(history_logs.created_at, '%M %e, %Y %l:%i %p') LIKE ?", ["%{$search}%"])
                ->orWhereRaw("DATE_FORMAT(history_logs.created_at, '%Y-%m-%d') LIKE ?", ["%{$search}%"]);

            return;
        }

        $query->orWhere('history_logs.created_at', 'like', "%{$search}%");
    }
}
