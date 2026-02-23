<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

interface DashboardRepositoryInterface
{
    public function productsQuery(): Builder;

    public function inventoriesQuery(): Builder;

    public function patientRecordsQuery(): Builder;

    public function productMovementsQuery(): Builder;

    public function findProduct(?int $id): ?Product;

    public function getFirstActiveProductId(): ?int;

    public function findBranchName(?int $id): ?string;
}

