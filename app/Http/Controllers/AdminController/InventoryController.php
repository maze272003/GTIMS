<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Contracts\InventoryServiceInterface;
use App\Helpers\ValidationHelper;
use App\Helpers\HistoryLogHelper;
use Exception;

class InventoryController extends Controller
{
    protected InventoryServiceInterface $inventoryService;

    public function __construct(InventoryServiceInterface $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // Show Inventory
    public function showinventory(Request $request)
    {
        $data = $this->inventoryService->getInventoryData($request);

        // AJAX Handling
        if ($request->ajax()) {
            $branch = $request->input('branch', 1);
            $selectedInventories = ($branch == 2) ? $data['inventories_rhu2'] : $data['inventories_rhu1'];

            return view('admin.partials._inventory_table', [
                'inventories' => $selectedInventories,
                'branch' => $branch
            ])->render();
        }

        return view('admin.inventory', $data);
    }

    // Fetch Archived Stocks
    public function fetchArchivedStocks(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = $request->input('product_id');
        $archivedstocks = $this->inventoryService->getArchivedStocks($productId);

        $html = '';
        if ($archivedstocks->isEmpty() && $request->page == 1) {
            $html = '<tr><td colspan="4" class="p-3 text-center text-red-500">No Archived Stocks Available</td></tr>';
        } else {
            foreach ($archivedstocks as $key => $stock) {
                $rowNumber = ($archivedstocks->currentPage() - 1) * $archivedstocks->perPage() + $key + 1;
                $expiryDate = \Carbon\Carbon::parse($stock->expiry_date)->format('M d, Y');

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
    public function addProduct(Request $request) {
        $validated = $request->validateWithBag(
            'addproduct',
            ValidationHelper::productRules(),
            ValidationHelper::productMessages()
        );

        try {
            $this->inventoryService->addProduct($validated);
            return to_route('admin.inventory')->with('success', 'Product added successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'addproduct');
        }
    }
    
    // UPDATE PRODUCT
    public function updateProduct(Request $request) {
        $validated = $request->validateWithBag(
            'updateproduct',
            array_merge(['product_id' => 'required|exists:products,id'], ValidationHelper::productRules()),
            array_merge(['product_id.required' => 'Product ID is required.', 'product_id.exists' => 'The selected product does not exist.'], ValidationHelper::productMessages())
        );

        try {
            $this->inventoryService->updateProduct($validated['product_id'], $validated);
            return to_route('admin.inventory')->with('success', 'Product updated successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'updateproduct');
        }
    }

    // ARCHIVE PRODUCT
    public function archiveProduct(Request $request) {
        $validated = $request->validateWithBag('archiveproduct', [
            'product_id' => 'required|exists:products,id',
        ], [
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',
        ]);

        try {
            $this->inventoryService->archiveProduct($validated['product_id']);
            return to_route('admin.inventory')->with('success', 'Product archived successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'archiveproduct');
        }
    }

    // UNARCHIVE PRODUCT
    public function unarchiveProduct(Request $request) {
        $validated = $request->validateWithBag('unarchiveproduct', [
            'product_id' => 'required|exists:products,id',
        ], [
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',
        ]);

        try {
            $this->inventoryService->unarchiveProduct($validated['product_id']);
            return to_route('admin.inventory')->with('success', 'Product unarchived successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'unarchiveproduct');
        }
    }
    
    // ADD STOCK
    public function addStock(Request $request) {
        $validated = $request->validateWithBag(
            'addstock',
            ValidationHelper::inventoryStockRules(),
            ValidationHelper::inventoryStockMessages()
        );

        try {
            $this->inventoryService->addStock($validated);
            return to_route('admin.inventory')->with('success', 'Stock added successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'addstock');
        }
    }

    // EDIT STOCK
    public function editStock(Request $request)
    {
        $validated = $request->validateWithBag(
            'editstock',
            ValidationHelper::inventoryEditStockRules(),
            ValidationHelper::inventoryStockMessages()
        );

        try {
            $this->inventoryService->editStock($validated['inventory_id'], $validated);
            return to_route('admin.inventory')->with('success', 'Stock updated successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'editstock');
        }
    }

    // TRANSFER STOCK
    public function transferStock(Request $request)
    {
        $validated = $request->validateWithBag(
            'transferstock',
            ValidationHelper::inventoryTransferStockRules(),
            [
                'inventory_id.required' => 'Inventory ID is required.',
                'inventory_id.exists' => 'Selected inventory does not exist.',
                'quantity.required' => 'Quantity is required.',
                'quantity.numeric' => 'Quantity must be a number.',
                'quantity.min' => 'Quantity must be at least 1.',
                'destination_branch.required' => 'Destination branch is required.',
                'destination_branch.in' => 'Invalid destination branch.',
            ]
        );

        try {
            $this->inventoryService->transferStock($validated['inventory_id'], $validated['quantity'], $validated['destination_branch']);
            return redirect()->route('admin.inventory')->with('success', 'Stock transferred successfully!');
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()], 'transferstock');
        }
    }
}
