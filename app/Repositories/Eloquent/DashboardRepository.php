<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Patientrecords;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function productsQuery(): Builder
    {
        return Product::query();
    }

    public function inventoriesQuery(): Builder
    {
        return Inventory::query();
    }

    public function patientRecordsQuery(): Builder
    {
        return Patientrecords::query();
    }

    public function productMovementsQuery(): Builder
    {
        return ProductMovement::query();
    }

    public function findProduct(?int $id): ?Product
    {
        if (!$id) {
            return null;
        }

        return Product::find($id);
    }

    public function getFirstActiveProductId(): ?int
    {
        return Product::where('is_archived', 0)->value('id');
    }

    public function findBranchName(?int $id): ?string
    {
        if (!$id) {
            return null;
        }

        return Branch::find($id)?->name;
    }
}

