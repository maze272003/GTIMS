<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Repositories\Interfaces\SupplierRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierRepositoryInterface $supplierRepository,
        protected ProductRepositoryInterface $productRepository
    ) {
    }

    public function index()
    {
        $suppliers = $this->supplierRepository->paginateWithProductCount(20);
        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    public function store(StoreSupplierRequest $request)
    {
        $this->supplierRepository->create($request->validated());
        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Supplier created.');
    }

    public function edit(int $id)
    {
        $supplier = $this->supplierRepository->findWithProducts($id);
        $allProducts = $this->productRepository->getActive();
        return view('admin.suppliers.edit', compact('supplier', 'allProducts'));
    }

    public function update(UpdateSupplierRequest $request, int $id)
    {
        $this->supplierRepository->update($id, $request->validated());
        return back()->with('success', 'Supplier updated.');
    }

    public function linkProduct(Request $request, int $supplierId)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'lead_time_days' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $this->supplierRepository->linkProduct(
            $supplierId,
            $validated['product_id'],
            $validated['lead_time_days'],
            $validated['unit_cost'] ?? null
        );

        return back()->with('success', 'Product linked to supplier.');
    }

    public function unlinkProduct(int $supplierId, int $productId)
    {
        $this->supplierRepository->unlinkProduct($supplierId, $productId);

        return back()->with('success', 'Product unlinked from supplier.');
    }
}
