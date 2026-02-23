<?php

namespace App\Repositories\Interfaces;

use App\Models\AuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuditEventRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate audit events with filtering.
     */
    public function paginateWithFilters(
        ?string $action = null,
        ?string $entityType = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20
    ): LengthAwarePaginator;

    public function getDistinctActions(): Collection;

    public function getDistinctEntityTypes(): Collection;
}
