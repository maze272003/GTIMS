<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\HistoryLog; // <-- added
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth; // <-- added
use App\Models\ProductMovement; // <-- ADD THIS

class InventoryController extends Controller
{
    
    // show inventory
    public function showinventory(Request $request)
    {
        $products = Product::where('is_archived', 1)->get();
        $archiveproducts = Product::where('is_archived', 1)->get();

        // Combined count for cards
        $inventorycount = Inventory::where('is_archived', 2)->get();

        // RHU 1
        $query1 = Inventory::where('branch_id', 1)->where('is_archived', 2);
        if ($request->filled('search_rhu1')) {
            $search = strtolower($request->search_rhu1);
            $query1->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(batch_number) LIKE ?', ["%{$search}%"])
                ->orWhereHas('product', fn($p) => $p->whereRaw('LOWER(generic_name) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(brand_name) LIKE ?', ["%{$search}%"]));
            });
        }
        $inventories_rhu1 = $query1->with('product')->paginate(20, ['*'], 'page_rhu1');

        // RHU 2
        $query2 = Inventory::where('branch_id', 2)->where('is_archived', 2);
        if ($request->filled('search_rhu2')) {
            $search = strtolower($request->search_rhu2);
            $query2->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(batch_number) LIKE ?', ["%{$search}%"])
                ->orWhereHas('product', fn($p) => $p->whereRaw('LOWER(generic_name) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(brand_name) LIKE ?', ["%{$search}%"]));
            });
        }
        $inventories_rhu2 = $query2->with('product')->paginate(20, ['*'], 'page_rhu2');

        if ($request->ajax()) {
            $branch = $request->input('branch', 1);
            $inventories = $branch == 1 ? $inventories_rhu1 : $inventories_rhu2;
            return view('admin.partials._inventory_table', [
                'inventories' => $inventories, 
                'branch' => $branch
                ])->render();
            }

        return view('admin.inventory', [
            'products' => $products,
            'archiveproducts' => $archiveproducts,
            'inventorycount' => $inventorycount,
            'inventories_rhu1' => $inventories_rhu1,
            'inventories_rhu2' => $inventories_rhu2
        ]);
    }

    // fetch archived stocks
    public function fetchArchivedStocks(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = $request->input('product_id');
        
        $archivedstocks = Inventory::where('is_archived', 1)
            ->where('product_id', $productId)
            ->orderBy('expiry_date', 'desc')
            ->paginate(20);

        $html = '';
        if ($archivedstocks->isEmpty() && $request->page == 1) {
            $html = '<tr><td colspan="4" class="p-3 text-center text-red-500">No Archived Stocks Available</td></tr>';
        } else {
            foreach ($archivedstocks as $key => $stock) {
                $rowNumber = ($archivedstocks->currentPage() - 1) * $archivedstocks->perPage() + $key + 1;
                $expiryDate = Carbon::parse($stock->expiry_date)->format('M d, Y');
                
                $html .= "<tr class=\"hover:bg-gray-50\">
                            <td class=\"text-left p-3\">{$rowNumber}</td>
                            <td class=\"text-left font-semibold text-gray-700\">{$stock->batch_number}</td>
                            <td class=\"text-left font-semibold text-gray-500 \">{$stock->quantity}</td>
                            <td class=\"text-center font-semibold text-gray-500\">{$expiryDate}</td>
                          </tr>";
            }
        }

        return response()->json([
            'html' => $html,
            'has_more_pages' => $archivedstocks->hasMorePages(), 
        ]);
    }

    // ADD PRODUCT
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

        // keep assignment so we can log the created product
        $newProduct = $product->create($validated);

        // minimal logging
        $user = Auth::user();
        HistoryLog::create([
            'action' => 'REGISTERED PRODUCT',
            'description' => "Registered a new product: {$newProduct->generic_name} ({$newProduct->brand_name} {$newProduct->form} - {$newProduct->strength})",
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'metadata' => [
                'product_id' => $newProduct->id,
            ],
        ]);

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

        // capture old values for logging
        $old = $product->only(['generic_name', 'brand_name', 'form', 'strength']);

        $product->update([
            'generic_name' => $validated['generic_name'],
            'brand_name' => $validated['brand_name'],
            'form' => $validated['form'],
            'strength' => $validated['strength'],
        ]);

        // minimal logging
        $user = Auth::user();
        HistoryLog::create([
            'action' => 'PRODUCT UPDATED',
            'description' => "Updated the product details for " . $old['generic_name'] . " " . $old['brand_name'] . " (" . $old['form'] . " - " . $old['strength'] . ") into " . $validated['generic_name'] . " " . $validated['brand_name'] . " (" . $validated['form'] . " - " . $validated['strength'] . ')',
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'metadata' => [
                'product_id' => $product->id,
            ],
        ]);

        return to_route('admin.inventory')->with('success', 'Product updated successfully.');
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

        // logging
        $user = Auth::user();
        HistoryLog::create([
            'action' => 'PRODUCT ARCHIVED',
            'description' => "{$product->generic_name} {$product->brand_name} ({$product->form} - {$product->strength}) has been archived and its corressponding stocks assigned to it.",
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'metadata' => [
                'product_id' => $product->id,
            ],
        ]);

        return to_route('admin.inventory')->with('success', 'Product archived successfully.');
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

        // logging
        $user = Auth::user();
        HistoryLog::create([
            'action' => 'PRODUCT UNARCHIVED',
            'description' => "{$product->generic_name} {$product->brand_name} ({$product->form} - {$product->strength}) has been unarchived and its corressponding stocks assigned to it.",
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'metadata' => [
                'product_id' => $product->id,
            ],
        ]);

        return to_route('admin.inventory')->with('success', 'Product unarchived successfully.');
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

        $user = Auth::user(); // for logging

        if ($existingStock) {
            $oldStock = $existingStock->quantity;
            $existingStock->quantity += $validated['quantity'];
            $existingStock->save();

            // === START: ADD THIS BLOCK ===
        ProductMovement::create([
            'product_id' => $existingStock->product_id,
            'inventory_id' => $existingStock->id,
            'user_id' => $user?->id,
            'type' => 'IN',
            'quantity' => $validated['quantity'], // The amount ADDED
            'quantity_before' => $oldStock,
            'quantity_after' => $existingStock->quantity, // The new total
            'description' => 'Manual stock addition (existing batch)',
        ]);
        // === END: ADD THIS BLOCK ===

            $product = Product::findOrFail($validated['product_id']);
            $oldQty = number_format($oldStock);
            $plannedQty = number_format($validated['quantity']);
            $addedQty = number_format($existingStock->quantity);

            // logging for quantity addition
            HistoryLog::create([
                'action' => 'STOCK ADDED',
                'description' => "Added additional stock (+{$plannedQty}) in batch no. {$existingStock->batch_number} (Product: {$product->generic_name} {$product->brand_name} [{$product->form} - {$product->strength}]). From {$oldQty} to {$addedQty}.",
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'metadata' => [
                    'inventory_id' => $existingStock->id,
                    'product_id' => $existingStock->product_id,
                ],
            ]);
        } else {
            $addstock = Inventory::create([
                'product_id' => $validated['product_id'],
                'batch_number' => $validated['batchnumber'],
                'quantity' => $validated['quantity'],
                'expiry_date' => $validated['expiry'],
            ]);

            // === START: ADD THIS BLOCK ===
        ProductMovement::create([
            'product_id' => $addstock->product_id,
            'inventory_id' => $addstock->id,
            'user_id' => $user?->id,
            'type' => 'IN',
            'quantity' => $addstock->quantity, // The amount ADDED
            'quantity_before' => 0, // It's a new batch
            'quantity_after' => $addstock->quantity, // The new total
            'description' => 'Manual stock addition (new batch)',
        ]);
        // === END: ADD THIS BLOCK ===

            // logging for new stock creation
            $prod = Product::findOrFail($validated['product_id']);

            $expry = Carbon::parse($addstock->expiry_date)->translatedFormat('M d, Y');
            $qty = number_format($addstock->quantity);

            HistoryLog::create([
                'action' => 'STOCK ADDED',
                'description' => "Created a new batch for {$prod->generic_name} {$prod->brand_name} ({$prod->form} - {$prod->strength}). Batch No. {$addstock->batch_number} with a qty of {$qty}. Expires in: {$expry}.",
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'metadata' => [
                    'inventory_id' => $addstock->id,
                    'product_id' => $addstock->product_id,
                ],
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

        $inventory = Inventory::with('product')
        ->findOrFail($validated['inventory_id']);

        // capture old values for logging
        $old = $inventory->only(['batch_number', 'quantity', 'expiry_date']);

        $inventory->update([
            'batch_number' => $validated['batchnumber'],
            'quantity'     => $validated['quantity'],
            'expiry_date'  => $validated['expiry'],
        ]);
        // === START: ADD THIS BLOCK ===
    $quantityChange = $validated['quantity'] - $old['quantity'];

    // Only log if the quantity actually changed
    if ($quantityChange != 0) {
        $movementType = $quantityChange > 0 ? 'IN' : 'OUT';
        $description = $quantityChange > 0 ? 'Manual stock adjustment (add)' : 'Manual stock adjustment (remove)';

        ProductMovement::create([
            'product_id' => $inventory->product_id,
            'inventory_id' => $inventory->id,
            'user_id' => Auth::id(),
            'type' => $movementType,
            'quantity' => abs($quantityChange), // The absolute amount that changed
            'quantity_before' => $old['quantity'],
            'quantity_after' => $validated['quantity'],
            'description' => $description,
        ]);
    }
    // === END: ADD THIS BLOCK ===

        // logging
        $prod = $inventory->product;
        $user = Auth::user();
        $expry = Carbon::parse($validated['expiry'])->translatedFormat('M d, Y');

        HistoryLog::create([
            'action' => 'STOCK UPDATED',
            'description' => "Updated the stock details from {$old['batch_number']} to {$validated['batchnumber']} (Product: {$prod->generic_name} {$prod->brand_name} [{$prod->form} - {$prod->strength}]). From qty {$old['quantity']} to {$validated['quantity']}. Now expires in: {$expry}.",
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'metadata' => [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
            ],
        ]);

        return to_route('admin.inventory')->with('success', 'Stock updated successfully.');
    }

}
