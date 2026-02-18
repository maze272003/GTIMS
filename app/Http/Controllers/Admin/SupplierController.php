<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Product;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount('products')->paginate(20);
        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        Supplier::create($validated);
        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('products');
        $allProducts = Product::where('is_archived', false)->get();
        return view('admin.suppliers.edit', compact('supplier', 'allProducts'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $supplier->update($validated);
        return back()->with('success', 'Supplier updated.');
    }

    public function linkProduct(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'lead_time_days' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        SupplierProduct::updateOrCreate(
            ['supplier_id' => $supplier->id, 'product_id' => $validated['product_id']],
            ['lead_time_days' => $validated['lead_time_days'], 'unit_cost' => $validated['unit_cost'] ?? null]
        );

        return back()->with('success', 'Product linked to supplier.');
    }

    public function unlinkProduct(Supplier $supplier, Product $product)
    {
        SupplierProduct::where('supplier_id', $supplier->id)
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('success', 'Product unlinked from supplier.');
    }
}
