<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SuppliersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Inventory;
use App\Repositories\Interfaces\SupplierRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierRepositoryInterface $supplierRepository
    ) {
    }

    public function index()
    {
        $suppliers = $this->supplierRepository->paginateWithProductCount(20);
        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();

        return Excel::download(
            new SuppliersExport($user),
            'suppliers_' . Carbon::now()->format('Ymd_His') . '.xlsx'
        );
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
        $supplier = $this->supplierRepository->findWithInventoryLinks($id);

        $linkedInventoryIds = $supplier->supplierProducts->pluck('inventory_id');

        $availableInventories = Inventory::query()
            ->with(['product', 'branch'])
            ->where('is_archived', false)
            ->whereHas('product', fn ($query) => $query->where('is_archived', false))
            ->when($linkedInventoryIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedInventoryIds))
            ->orderBy('expiry_date')
            ->orderBy('batch_number')
            ->get()
            ->sortBy(function (Inventory $inventory) {
                $productLabel = strtolower((string) ($inventory->product?->generic_name ?? $inventory->product?->brand_name ?? ''));
                $branchLabel = strtolower((string) ($inventory->branch?->name ?? ''));

                return "{$productLabel}|{$branchLabel}|".strtolower((string) $inventory->batch_number);
            })
            ->values();

        return view('admin.suppliers.edit', compact('supplier', 'availableInventories'));
    }

    public function update(UpdateSupplierRequest $request, int $id)
    {
        $this->supplierRepository->update($id, $request->validated());
        return back()->with('success', 'Supplier updated.');
    }

    public function linkInventory(Request $request, int $supplierId)
    {
        $validated = $request->validate([
            'inventory_id' => 'required|exists:inventories,id',
            'lead_time_days' => 'nullable|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $this->supplierRepository->linkInventory(
            $supplierId,
            $validated['inventory_id'],
            $validated['lead_time_days'] ?? null,
            $validated['unit_cost'] ?? null
        );

        return back()->with('success', 'Inventory batch linked to supplier.');
    }

    public function unlinkInventory(int $supplierId, int $inventoryId)
    {
        $this->supplierRepository->unlinkInventory($supplierId, $inventoryId);

        return back()->with('success', 'Inventory batch unlinked from supplier.');
    }
}
