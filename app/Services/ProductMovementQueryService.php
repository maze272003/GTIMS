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
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function export(array $params): BinaryFileResponse
    {
        $fileName = 'movements_report_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new ProductMovementsExport($params), $fileName);
    }

    public function getIndexData(array $filters): array
    {
        $movements = $this->productMovementRepository
            ->paginateWithFilters($filters, 20)
            ->withQueryString();

        $stats = $this->productMovementRepository->getTodayStats();

        return [
            'movements' => $movements,
            'products' => $this->productRepository->getActive()->sortBy('generic_name')->values(),
            'users' => $this->userRepository->getAllOrderedByName(),
            ...$stats,
        ];
    }
}

