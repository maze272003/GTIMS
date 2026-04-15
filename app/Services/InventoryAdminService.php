<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Branch;
use App\Models\HistoryLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ProductMovement;
use App\Repositories\Interfaces\InventoryAdminRepositoryInterface;
use Illuminate\Validation\Rule;

class InventoryAdminService
{
    public function __construct(
        protected InventoryAdminRepositoryInterface $inventoryAdminRepository,
        protected BranchAccessService $branchAccessService
    ) {
    }

    // show inventory
    // Inside App\Http\Controllers\AdminController\InventoryController.php

public function showinventory(Request $request)
{
    $user = $request->user();
    $visibleBranchIds = $this->branchAccessService->accessibleBranchIds($user);
    $focusInventoryId = $request->integer('focus_inventory_id');
    $focusedInventory = null;
    $focusBranch = null;

    if ($focusInventoryId) {
        $focusedInventory = $this->inventoryAdminRepository->getFocusInventoryWithProduct($focusInventoryId);

        if ($focusedInventory) {
            $this->branchAccessService->authorizeBranchAccess($user, $focusedInventory->branch_id, 'view inventory from another branch');
            $focusBranch = (int) $focusedInventory->branch_id;
            $focusSearchKey = 'search_branch_' . $focusBranch;

            if (!$request->filled($focusSearchKey)) {
                $request->merge([
                    $focusSearchKey => $focusedInventory->batch_number,
                ]);
            }
        }
    }

    // 1. Common Data
    $products = $this->inventoryAdminRepository->getActiveProducts();
    $archiveproducts = $this->inventoryAdminRepository->getArchivedProducts();
    $branches = $this->inventoryAdminRepository->getSupportedBranches($visibleBranchIds);
    $inventoryStats = $this->inventoryAdminRepository->getInventoryOverviewStats($visibleBranchIds);

    $branchInventories = [];

    foreach ($branches as $branch) {
        $branchId = (int) $branch->id;
        $searchKey = 'search_branch_'.$branchId;
        $filterKey = 'filter_branch_'.$branchId;
        $pageKey = 'page_branch_'.$branchId;
        $query = $this->inventoryAdminRepository->buildActiveInventoryByBranchQuery(
            $branchId,
            $request->input($searchKey),
            $request->input($filterKey)
        );

        if ($focusedInventory && $focusBranch === $branchId) {
            $query->where('inventories.id', $focusedInventory->id);
        }

        $branchInventories[$branchId] = $query->paginate(20, ['inventories.*'], $pageKey);
    }

    // 4. AJAX Handling
    if ($request->ajax()) {
        $defaultBranch = (int) ($branches->first()?->id ?? 0);
        $branchId = (int) $request->input('branch', $defaultBranch);
        $selectedInventories = $branchInventories[$branchId] ?? null;

        if (!$selectedInventories) {
            abort(404, 'Branch not found.');
        }

        return view('admin.partials._inventory_table', [
            'inventories' => $selectedInventories,
            'branch' => $branchId,
            'focusInventoryId' => $focusedInventory?->id,
        ])->render();
    }

    // 5. Normal View Return
    return view('admin.inventory', [
        'products' => $products,
        'archiveproducts' => $archiveproducts,
        'branches' => $branches,
        'inventoryStats' => $inventoryStats,
        'branchInventories' => $branchInventories,
        'focusInventoryId' => $focusedInventory?->id,
        'focusBranch' => $focusBranch,
    ]);
}

    // fetch archived stocks
    public function fetchArchivedStocks(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = $request->input('product_id');
        $visibleBranchIds = $this->branchAccessService->accessibleBranchIds($request->user());

        $archivedstocks = $this->inventoryAdminRepository->paginateArchivedStocksByProduct((int) $productId, $visibleBranchIds, 20);

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
            'generic_name.required' => 'Generic name is required.',
            'brand_name.required.message' => 'Brand name is required.',
            'form.required.message' => 'Form is required.',
            'strength.required.message' => 'Strength is required.',
        ]);

        // keep assignment so we can log the created product
        $newProduct = $this->inventoryAdminRepository->createProduct($validated);

        // minimal logging
        $user = Auth::user();
        $this->inventoryAdminRepository->createHistoryLog([
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

        $product = $this->inventoryAdminRepository->findProductOrFail((int) $validated['product_id']);

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
        $this->inventoryAdminRepository->createHistoryLog([
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

        $product = $this->inventoryAdminRepository->findProductOrFail((int) $validated['product_id']);

        DB::transaction(function () use ($product) {
            $product->update(['is_archived' => 1]);
            $this->inventoryAdminRepository->updateStocksArchiveStateByProduct($product->id, 1);
        });

        $user = Auth::user();
        $this->inventoryAdminRepository->createHistoryLog([
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

        $product = $this->inventoryAdminRepository->findProductOrFail((int) $validated['product_id']);

        DB::transaction(function () use ($product) {
            $product->update(['is_archived' => 0]);
            $this->inventoryAdminRepository->updateStocksArchiveStateByProduct($product->id, 0);
        });

        $user = Auth::user();
        $this->inventoryAdminRepository->createHistoryLog([
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
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'batchnumber' => 'required|min:3|max:120',
            'quantity' => 'required|numeric',
            'expiry' => 'required|date',
        ], [
            'product_id.required'=> 'Product ID is required.',
            'branch_id.required'=> 'Branch ID is required.',
            'branch_id.exists'=> 'The selected branch does not exist.',
            'batchnumber.required'=> 'Batch number is required.',
            'quantity.required'=> 'Quantity is required.',
            'expiry.required'=> 'Expiry date is required.',
        ]);

        $validated['branch_id'] = $this->branchAccessService->resolveBranchFilter($request->user(), $validated['branch_id']);

        $branchName = $this->inventoryAdminRepository->findBranchName((int) $validated['branch_id']) ?? ('Branch #' . $validated['branch_id']);
        $user = Auth::user();

        $existingStock = $this->inventoryAdminRepository->findExistingStock(
            (int) $validated['product_id'],
            $validated['batchnumber'],
            $validated['expiry'],
            (int) $validated['branch_id']
        );

        if ($existingStock) {
            DB::transaction(function () use ($existingStock, $validated, $user) {
                $lockedStock = Inventory::where('id', $existingStock->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldStock = $lockedStock->quantity;
                $lockedStock->quantity += $validated['quantity'];
                $lockedStock->save();

                $this->inventoryAdminRepository->createProductMovement([
                    'product_id' => $lockedStock->product_id,
                    'inventory_id' => $lockedStock->id,
                    'user_id' => $user?->id,
                    'type' => 'IN',
                    'quantity' => $validated['quantity'],
                    'quantity_before' => $oldStock,
                    'quantity_after' => $lockedStock->quantity,
                    'description' => 'Manual stock addition (existing batch)',
                ]);
            });

            $existingStock->refresh();
            $product = $this->inventoryAdminRepository->findProductOrFail((int) $validated['product_id']);
            $oldQty = number_format($existingStock->quantity - $validated['quantity']);
            $plannedQty = number_format($validated['quantity']);
            $addedQty = number_format($existingStock->quantity);

            $this->inventoryAdminRepository->createHistoryLog([
                'action' => 'STOCK ADDED',
                'description' => "Added additional stock (+{$plannedQty}) in {$branchName}, batch no. {$existingStock->batch_number} (Product: {$product->generic_name} {$product->brand_name} [{$product->form} - {$product->strength}]). From {$oldQty} to {$addedQty}.",
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'metadata' => [
                    'inventory_id' => $existingStock->id,
                    'product_id' => $existingStock->product_id,
                ],
            ]);
        } else {
            $addstock = DB::transaction(function () use ($validated, $user) {
                $stock = $this->inventoryAdminRepository->createInventory([
                    'product_id' => $validated['product_id'],
                    'branch_id' => $validated['branch_id'],
                    'batch_number' => $validated['batchnumber'],
                    'quantity' => $validated['quantity'],
                    'expiry_date' => $validated['expiry'],
                    'is_archived' => 0,
                ]);

                $this->inventoryAdminRepository->createProductMovement([
                    'product_id' => $stock->product_id,
                    'inventory_id' => $stock->id,
                    'user_id' => $user?->id,
                    'type' => 'IN',
                    'quantity' => $stock->quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $stock->quantity,
                    'description' => 'Manual stock addition (new batch)',
                ]);

                return $stock;
            });

            $prod = $this->inventoryAdminRepository->findProductOrFail((int) $validated['product_id']);
            $expry = Carbon::parse($addstock->expiry_date)->translatedFormat('M d, Y');
            $qty = number_format($addstock->quantity);

            $this->inventoryAdminRepository->createHistoryLog([
                'action' => 'STOCK ADDED',
                'description' => "Created a new batch for {$prod->generic_name} {$prod->brand_name} ({$prod->form} - {$prod->strength}) in {$branchName}. Batch No. {$addstock->batch_number} with a qty of {$qty}. Expires in: {$expry}.",
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

        $inventory = $this->inventoryAdminRepository->findInventoryWithProductOrFail((int) $validated['inventory_id']);
        $this->branchAccessService->authorizeBranchAccess($request->user(), $inventory->branch_id, 'edit inventory from another branch');

        $old = $inventory->only(['batch_number', 'quantity', 'expiry_date']);

        DB::transaction(function () use ($inventory, $validated, $old) {
            $lockedInventory = Inventory::where('id', $inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInventory->update([
                'batch_number' => $validated['batchnumber'],
                'quantity'     => $validated['quantity'],
                'expiry_date'  => $validated['expiry'],
            ]);

            $quantityChange = $validated['quantity'] - $old['quantity'];

            if ($quantityChange != 0) {
                $movementType = $quantityChange > 0 ? 'IN' : 'OUT';
                $description = $quantityChange > 0 ? 'Manual stock adjustment (add)' : 'Manual stock adjustment (remove)';

                $this->inventoryAdminRepository->createProductMovement([
                    'product_id' => $lockedInventory->product_id,
                    'inventory_id' => $lockedInventory->id,
                    'user_id' => Auth::id(),
                    'type' => $movementType,
                    'quantity' => abs($quantityChange),
                    'quantity_before' => $old['quantity'],
                    'quantity_after' => $validated['quantity'],
                    'description' => $description,
                ]);
            }
        });

        $inventory->refresh();
        $prod = $inventory->product;
        $user = Auth::user();
        $expry = Carbon::parse($validated['expiry'])->translatedFormat('M d, Y');

        $this->inventoryAdminRepository->createHistoryLog([
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

    public function transferStock(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|exists:inventories,id',
            'quantity'     => 'required|numeric|min:1',
            'destination_branch' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ]);

        $sourceInventory = $this->inventoryAdminRepository->findInventoryWithProductOrFail((int) $request->inventory_id);
        $this->branchAccessService->authorizeBranchAccess($request->user(), $sourceInventory->branch_id, 'transfer stock from another branch');
        $destinationBranchId = (int) $request->destination_branch;
        $this->branchAccessService->authorizeBranchAccess($request->user(), $destinationBranchId, 'transfer stock into another branch');

        if ((int) $sourceInventory->branch_id === $destinationBranchId) {
            return back()->with('error', 'Destination branch must be different from source branch.');
        }

        $sourceBranchName = $sourceInventory->branch?->name
            ?? ('Branch #'.$sourceInventory->branch_id);
        $destinationBranchName = $this->inventoryAdminRepository->findBranchName($destinationBranchId)
            ?? ('Branch #'.$destinationBranchId);

        $transferQty = $request->quantity;

        DB::transaction(function () use ($sourceInventory, $destinationBranchId, $transferQty) {
            $lockedSource = Inventory::where('id', $sourceInventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSource->quantity < $transferQty) {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], []),
                    new \Illuminate\Support\MessageBag(['quantity' => 'Not enough stock to transfer.'])
                );
            }

            $lockedSource->quantity -= $transferQty;
            $lockedSource->save();

            $destInventory = Inventory::where('product_id', $sourceInventory->product_id)
                ->where('batch_number', $sourceInventory->batch_number)
                ->where('branch_id', $destinationBranchId)
                ->where('expiry_date', $sourceInventory->expiry_date)
                ->lockForUpdate()
                ->first();

            if ($destInventory) {
                $destInventory->quantity += $transferQty;
                $destInventory->save();
            } else {
                Inventory::create([
                    'product_id'    => $sourceInventory->product_id,
                    'batch_number'  => $sourceInventory->batch_number,
                    'quantity'      => $transferQty,
                    'expiry_date'   => $sourceInventory->expiry_date,
                    'branch_id'     => $destinationBranchId,
                    'is_archived'   => 0,
                ]);
            }
        });

        $sourceInventory->refresh();

        $this->inventoryAdminRepository->createProductMovement([
            'product_id' => $sourceInventory->product_id,
            'inventory_id' => $sourceInventory->id,
            'user_id' => Auth::id(),
            'type' => 'OUT',
            'quantity' => $transferQty,
            'quantity_before' => $sourceInventory->quantity + $transferQty,
            'quantity_after' => $sourceInventory->quantity,
            'description' => "Stock transfer from {$sourceBranchName} to {$destinationBranchName}.",
        ]);

        $destInventory = $this->inventoryAdminRepository->findTransferDestinationStock($sourceInventory, $destinationBranchId);

        $this->inventoryAdminRepository->createProductMovement([
            'product_id' => $sourceInventory->product_id,
            'inventory_id' => $destInventory?->id,
            'user_id' => Auth::id(),
            'type' => 'IN',
            'quantity' => $transferQty,
            'quantity_before' => ($destInventory?->quantity ?? 0) - $transferQty,
            'quantity_after' => $destInventory?->quantity ?? $transferQty,
            'description' => "Stock received from {$sourceBranchName} to {$destinationBranchName}.",
        ]);

        return redirect()->route('admin.inventory')->with('success', 'Stock transferred successfully!');
    }
}
