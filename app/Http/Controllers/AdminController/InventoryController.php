<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Inventory;
use Carbon\Carbon;
// use pagination
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryController extends Controller
{
    
    public function showinventory(Request $request)
    {
        // Kunin ang search term mula sa URL (e.g., ?search=amoxicillin)
        $search = $request->input('search', '');

        // Simulan ang query
        $inventoryQuery = Inventory::where('is_archived', 2);

        // Kung may search term, i-filter ang query
        if (!empty($search)) {
            $inventoryQuery->where(function ($query) use ($search) {
                $query->where('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('generic_name', 'like', "%{$search}%")
                            ->orWhere('brand_name', 'like', "%{$search}%")
                            ->orWhere('form', 'like', "%{$search}%")
                            ->orWhere('strength', 'like', "%{$search}%");
                    });
            });
        }

        // Paginate the results
        // Gagamitin natin ang withQueryString() para maalala ng pagination links ang search query
        $inventories = $inventoryQuery->paginate(10)->withQueryString();

        // Kung AJAX, ibalik lang ang partial view ng table
        if ($request->ajax()) {
            return view('admin.partials._inventory_table', ['inventories' => $inventories])->render();
        }

        // Para sa normal page load, kunin ang data (MALIBAN SA ARCHIVED STOCKS)
        $products = Product::where('is_archived', 2)->get();
        $archiveproducts = Product::where('is_archived', 1)->get();
        // $archivedstocks = Inventory::where('is_archived', 1)->paginate(20); // <-- ALISIN ITO

        // Ibalik ang buong view (NA WALANG ARCHIVED STOCKS)
        return view('admin.inventory', [
            'products' => $products, 
            'inventories' => $inventories,
            'archiveproducts' => $archiveproducts,
            // 'archivedstocks' => $archivedstocks, // <-- ALISIN DIN ITO
        ]);
    }

    public function fetchArchivedStocks(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = $request->input('product_id');
        
        $archivedstocks = Inventory::where('is_archived', 1)
            ->where('product_id', $productId)
            ->orderBy('expiry_date', 'desc')
            ->paginate(20); // Limit to 20 per request

        $html = '';
        if ($archivedstocks->isEmpty() && $request->page == 1) {
            $html = '<tr><td colspan="4" class="p-3 text-center text-red-500">No Archived Stocks Available</td></tr>';
        } else {
            foreach ($archivedstocks as $key => $stock) {
                $rowNumber = ($archivedstocks->currentPage() - 1) * $archivedstocks->perPage() + $key + 1;
                $expiryDate = Carbon::parse($stock->expiry_date)->format('M d, Y');
                
                $html .= "<tr class=\"hover:bg-gray-50 dark:hover:bg-gray-700\">
                            <td class=\"text-left p-3 dark:text-gray-300\">{$rowNumber}</td>
                            <td class=\"text-left font-semibold text-gray-700 dark:text-gray-200\">{$stock->batch_number}</td>
                            <td class=\"text-left font-semibold text-gray-500 dark:text-gray-400\">{$stock->quantity}</td>
                            <td class=\"text-center font-semibold text-gray-500 dark:text-gray-400\">{$expiryDate}</td>
                          </tr>";
            }
        }

        return response()->json([
            'html' => $html,
            'has_more_pages' => $archivedstocks->hasMorePages(), 
        ]);
    }

    public function addProduct(Request $request, Product $product) {
        $validated = $request->validateWithBag( 'addproduct', [
            'generic_name' => 'min:3|max:120|required',
            'brand_name' => 'min:3|max:120|required',
            'form' => 'min:3|max:120|required',
            'strength' => 'min:3|max:120|required',
        ], [
            'generic_name.required.message' => 'Generic name is required.',
            'brand_name.required.message' => 'Brand name is required.',
            'form.required.message' => 'Form is required.',
            'strength.required.message' => 'Strength is required.',
        ]);

        $product->create($validated);

        return to_route('admin.inventory')->with('success', 'Product added successfully.');
    }
    
    // UPDATE PRODUCT

    public function updateProduct(Request $request) {
        $validated = $request->validateWithBag( 'updateproduct', [
            'product_id' => 'required|exists:products,id',
            'generic_name' => 'required|min:3|max:120',
            'brand_name' => 'required|min:3|max:120',
            'form' => 'required|min:3|max:120',
            'strength' => 'required|min:3|max:120',
        ], [
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',
            'generic_name.required' => 'Generic name is required.',
            'brand_name.required' => 'Brand name is required.',
            'form.required' => 'Form is required.',
            'strength.required' => 'Strength is required.',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $product->update([
            'generic_name' => $validated['generic_name'],
            'brand_name' => $validated['brand_name'],
            'form' => $validated['form'],
            'strength' => $validated['strength'],
        ]);

        return redirect()->route('admin.inventory')->with('success', 'Product updated successfully.');
    }

    // ARCHIVE PRODUCT

    public function archiveProduct(Request $request) {
        $validated = $request->validateWithBag('archiveproduct', [
            'product_id' => 'required|exists:products,id',
        ], [
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $product->update([
            'is_archived' => 1,
        ]);

        // Archive stock that belongs to the product
        Inventory::where('product_id', $product->id)->update([
            'is_archived' => 1,
        ]);

        return redirect()->route('admin.inventory')->with('success', 'Product archived successfully.');
    }

    // UNARCHIVE PRODUCT

    public function unarchiveProduct(Request $request) {
        $validated = $request->validateWithBag('unarchiveproduct', [
            'product_id' => 'required|exists:products,id',
        ], [
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $product->update([
            'is_archived' => 2,
        ]);

        // Unarchive stock that belongs to the product
        Inventory::where('product_id', $product->id)->update([
            'is_archived' => 2,
        ]);

        return redirect()->route('admin.inventory')->with('success', 'Product unarchived successfully.');
    }
    // ADD STOCK

    public function addStock(Request $request) {
        $validated = $request->validateWithBag( 'addstock', [
            'product_id' => 'required|exists:products,id',
            'batchnumber' => 'required|min:3|max:120',
            'quantity' => 'required|numeric',
            'expiry' => 'required|date',
        ], [
            'product_id.required'=> 'Product ID is required.',
            'batchnumber.required'=> 'Batch number is required.',
            'quantity.required'=> 'Quantity is required.',
            'expiry.required'=> 'Expiry date is required.',
        ]);

        $existingStock = Inventory::where('product_id', $validated['product_id'])
            ->where('batch_number', $validated['batchnumber'])
            ->where('expiry_date', $validated['expiry'])
            ->first();

        if ($existingStock) {
            $existingStock->quantity += $validated['quantity'];
            $existingStock->save();
        } else {
            $addstock = Inventory::create([
                'product_id' => $validated['product_id'],
                'batch_number' => $validated['batchnumber'],
                'quantity' => $validated['quantity'],
                'expiry_date' => $validated['expiry'],
            ]);
        }

        return to_route('admin.inventory')->with('success', 'Stock added successfully.');
    }

    // EDIT STOCK

    public function editStock(Request $request)
    {
        $validated = $request->validateWithBag('editstock', [
            'inventory_id' => 'required|exists:inventories,id',
            'batchnumber' => 'required|min:3|max:120',
            'quantity' => 'required|numeric|min:0',
            'expiry' => 'required|date|after:today',
        ], [
            'inventory_id.required' => 'Product ID is required.',
            'inventory_id.exists'   => 'The selected stock does not exist.',
            'batchnumber.required'  => 'Batch number is required.',
            'quantity.required'     => 'Quantity is required.',
            'quantity.numeric'      => 'Quantity must be a number.',
            'expiry.required'       => 'Expiry date is required.',
            'expiry.date'           => 'Expiry date must be a valid date.',
            'expiry.after'          => 'Expiry date cannot be in the past.',
        ]);

        $inventory = Inventory::findOrFail($validated['inventory_id']);

        $inventory->update([
            'batch_number' => $validated['batchnumber'],
            'quantity'     => $validated['quantity'],
            'expiry_date'  => $validated['expiry'],
        ]);

        return redirect()
            ->route('admin.inventory')
            ->with('success', 'Stock updated successfully.');
    }

}

