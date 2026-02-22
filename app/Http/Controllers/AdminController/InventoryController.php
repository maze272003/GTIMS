<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryAdminService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryAdminService $inventoryAdminService
    ) {
    }

    public function showinventory(Request $request)
    {
        return $this->inventoryAdminService->showinventory($request);
    }

    public function fetchArchivedStocks(Request $request)
    {
        return $this->inventoryAdminService->fetchArchivedStocks($request);
    }

    public function addProduct(Request $request, Product $product)
    {
        return $this->inventoryAdminService->addProduct($request, $product);
    }

    public function updateProduct(Request $request)
    {
        return $this->inventoryAdminService->updateProduct($request);
    }

    public function archiveProduct(Request $request)
    {
        return $this->inventoryAdminService->archiveProduct($request);
    }

    public function unarchiveProduct(Request $request)
    {
        return $this->inventoryAdminService->unarchiveProduct($request);
    }

    public function addStock(Request $request)
    {
        return $this->inventoryAdminService->addStock($request);
    }

    public function editStock(Request $request)
    {
        return $this->inventoryAdminService->editStock($request);
    }

    public function transferStock(Request $request)
    {
        return $this->inventoryAdminService->transferStock($request);
    }
}

