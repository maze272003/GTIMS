<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditEvent;
use App\Repositories\Interfaces\AuditEventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditEventRepository extends BaseRepository implements AuditEventRepositoryInterface
{
    public function __construct(AuditEvent $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(
        ?string $action = null,
        ?string $entityType = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20,
        ?int $branchId = null
    ): LengthAwarePaginator {
        return $this->model
            ->with('user')
            ->when($branchId, fn ($q, $branch) => $q->whereHas('user', fn ($userQuery) => $userQuery->where('branch_id', $branch)))
            ->when($action, fn ($q, $a) => $q->where('action', $a))
            ->when($entityType, fn ($q, $e) => $q->where('entity_type', $e))
            ->when($userId, fn ($q, $u) => $q->where('user_id', $u))
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate($perPage);
    }

    public function getDistinctActions(?int $branchId = null): Collection
    {
        return $this->model->newQuery()
            ->when($branchId, fn ($q, $branch) => $q->whereHas('user', fn ($userQuery) => $userQuery->where('branch_id', $branch)))
            ->select('action')
            ->distinct()
            ->pluck('action');
    }

    public function getDistinctEntityTypes(?int $branchId = null): Collection
    {
        return $this->model->newQuery()
            ->when($branchId, fn ($q, $branch) => $q->whereHas('user', fn ($userQuery) => $userQuery->where('branch_id', $branch)))
            ->select('entity_type')
            ->distinct()
            ->pluck('entity_type');
    }
}
