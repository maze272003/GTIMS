<?php

namespace App\Services;

use App\Exports\ProductMovementsExport;
use App\Repositories\Interfaces\ProductMovementRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductMovementQueryService
{
    public function __construct(
        protected ProductMovementRepositoryInterface $productMovementRepository,
        protected ProductRepositoryInterface $productRepository,
        protected UserRepositoryInterface $userRepository,
        protected BranchAccessService $branchAccessService
    ) {
    }

    public function export(array $params): BinaryFileResponse
    {
        $params['branch_id'] = $this->branchAccessService->resolveBranchFilter(auth()->user(), $params['branch_id'] ?? null, defaultToUserBranch: true);
        $fileName = 'movements_report_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new ProductMovementsExport($params), $fileName);
    }

    public function getIndexData(array $filters): array
    {
        $branchId = $this->branchAccessService->resolveBranchFilter(auth()->user(), $filters['branch_id'] ?? null, defaultToUserBranch: true);
        $filters['branch_id'] = $branchId;

        $movements = $this->productMovementRepository
            ->paginateWithFilters($filters, 20)
            ->withQueryString();

        $stats = $this->productMovementRepository->getTodayStats($branchId);

        return [
            'movements' => $movements,
            'products' => $this->productRepository->getActive()->sortBy('generic_name')->values(),
            'users' => $this->userRepository->getAllOrderedByName(),
            'branches' => $this->branchAccessService->visibleBranches(auth()->user()),
            ...$stats,
        ];
    }
}
