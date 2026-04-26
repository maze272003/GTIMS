<?php

namespace App\Services;

use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HistoryLogQueryService
{
    public function __construct(
        protected HistoryLogRepositoryInterface $historyLogRepository,
        protected BranchAccessService $branchAccessService
    ) {
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $branchId = $this->branchAccessService->resolveBranchFilter(auth()->user(), null, defaultToUserBranch: true);

        return $this->historyLogRepository->paginateWithFilters($filters, $perPage, $branchId)->withQueryString();
    }

    public function getFilterOptions(): array
    {
        $branchId = $this->branchAccessService->resolveBranchFilter(auth()->user(), null, defaultToUserBranch: true);

        return [
            'actions' => $this->historyLogRepository->getDistinctActions($branchId),
            'users' => $this->historyLogRepository->getDistinctUsers($branchId),
        ];
    }
}
