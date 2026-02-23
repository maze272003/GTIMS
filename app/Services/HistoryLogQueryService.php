<?php

namespace App\Services;

use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HistoryLogQueryService
{
    public function __construct(
        protected HistoryLogRepositoryInterface $historyLogRepository
    ) {
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->historyLogRepository->paginateWithFilters($filters, $perPage)->withQueryString();
    }

    public function getFilterOptions(): array
    {
        return [
            'actions' => $this->historyLogRepository->getDistinctActions(),
            'users' => $this->historyLogRepository->getDistinctUsers(),
        ];
    }
}

