<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Inventory;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderAdminService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * 1. Show the Ordering Page (Create Form)
     * FIXES: Groups batches together so products don't appear twice.
     */

   private function checkAccess()
    {
        if (!Auth::user()->hasPermission('orders.view')) {
            abort(403, 'Unauthorized Access to Orders.');
        }
    }

   
public function create()
{
    $this->checkAccess();

    $user = Auth::user();
    $currentBranchId = $user->branch_id;
    $branches = Branch::query()->active()->orderBy('name')->get();

    // 1. Fetch ALL active inventory grouped by Product and Branch.
    $rawInventory = $this->orderRepository->getGroupedActiveInventoryTotals();

    // 2. Build a dynamic StockMap.
    // Structure: [ productId => ['branches' => [branchId => qty], 'total' => qty] ]
    $stockMap = [];
    foreach($rawInventory as $item) {
        $pid = $item->product_id;

        if (!isset($stockMap[$pid])) {
            $stockMap[$pid] = ['branches' => [], 'total' => 0];
        }

        $stockMap[$pid]['branches'][(int) $item->branch_id] = (int) $item->total_qty;
        $stockMap[$pid]['total'] += (int)$item->total_qty;
    }

    // 3. Generate Suggested Items (Low Stock logic for current user's branch).
    $products = $this->orderRepository->getActiveProductsOrdered();
    $suggestedItems = [];

    foreach($products as $product) {
        $stats = $stockMap[$product->id] ?? ['branches' => [], 'total' => 0];
        $myBranchStock = (int) ($stats['branches'][$currentBranchId] ?? 0);

        if ($myBranchStock <= 100) {
            $suggestedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->generic_name . ' (' . $product->brand_name . ')',
                'branch_stocks' => $stats['branches'],
                'total_stock' => $stats['total'],
                'suggested_qty' => 1000 - $myBranchStock
            ];
        }
    }

    return view('admin.orders.create', [
        'suggestedItems' => $suggestedItems,
        'allProducts' => $products,
        'stockMap' => $stockMap,
        'branches' => $branches,
        'defaultSourceBranchId' => (int) old('source_branch_id', $currentBranchId),
    ]);
}

    public function sourceInventoryOptions(Request $request)
    {
        $this->checkAccess();

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ]);

        $branchId = (int) $validated['branch_id'];
        $inventoryRows = $this->orderRepository->getAvailableSourceInventoryByBranch($branchId);
        $grouped = [];

        foreach ($inventoryRows as $row) {
            $productId = (int) $row->product_id;
            $grouped[$productId] ??= [];
            $grouped[$productId][] = [
                'inventory_id' => (int) $row->id,
                'product_id' => $productId,
                'batch_number' => (string) $row->batch_number,
                'available_quantity' => (int) ($row->available_qty ?? 0),
                'expiry_date' => optional($row->expiry_date)->format('Y-m-d'),
                'received_date' => optional($row->created_at)->format('Y-m-d'),
                'label' => sprintf(
                    'Batch #%s • Exp: %s • Avail: %d • Recv: %s',
                    $row->batch_number ?: 'N/A',
                    optional($row->expiry_date)->format('Y-m-d') ?? '-',
                    (int) ($row->available_qty ?? 0),
                    optional($row->created_at)->format('Y-m-d') ?? '-'
                ),
            ];
        }

        return response()->json([
            'branch_id' => $branchId,
            'inventory_by_product' => $grouped,
        ]);
    }
    /**
     * 2. Store the Order (Pharmacist/Admin Action)
     */
    public function store(Request $request)
    {
        $this->checkAccess();

        $validator = Validator::make($request->all(), [
            'source_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.source_inventory_id' => [
                'required',
                'integer',
                Rule::exists('inventories', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ]);

        $validator->after(function ($validator) use ($request) {
            $sourceBranchId = (int) $request->input('source_branch_id');
            $items = $request->input('items', []);

            foreach ($items as $index => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $sourceInventoryId = (int) ($item['source_inventory_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);

                if (!$productId || !$sourceInventoryId || !$quantity) {
                    continue;
                }

                $inventory = Inventory::query()
                    ->where('id', $sourceInventoryId)
                    ->where('is_archived', false)
                    ->whereHas('branch', fn ($query) => $query->where('is_archived', false))
                    ->whereHas('product', fn ($query) => $query->where('is_archived', false))
                    ->first();

                if (!$inventory) {
                    $validator->errors()->add("items.{$index}.source_inventory_id", 'Selected source batch is unavailable.');
                    continue;
                }

                if ((int) $inventory->branch_id !== $sourceBranchId) {
                    $validator->errors()->add("items.{$index}.source_inventory_id", 'Selected source batch does not belong to the selected source branch.');
                }

                if ((int) $inventory->product_id !== $productId) {
                    $validator->errors()->add("items.{$index}.source_inventory_id", 'Selected source batch does not match the chosen product.');
                }

                $onHand = (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0);
                $held = (int) ($inventory->hold_qty ?? 0);
                $available = max(0, $onHand - $held);

                if ($quantity > $available) {
                    $validator->errors()->add("items.{$index}.quantity", "Quantity exceeds available stock ({$available}) for the selected batch.");
                }
            }
        });

        $validator->validate();

        try {
            $this->orderRepository->createOrderWithItems(
                (int) Auth::user()->branch_id,
                (int) $request->integer('source_branch_id'),
                (int) Auth::id(),
                $request->remarks,
                $request->items
            );

            return redirect()->route('admin.orders.index')
                ->with('success', 'Order submitted successfully! Waiting for Admin approval.');

        } catch (\Exception $e) {
            Log::error('Order Store Error: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'Failed to submit order. Please try again.');
        }
    }

    /**
     * 3. List Orders (Index)
     */
    public function index()
    {
                $this->checkAccess(); // <--- Security Check

        $user = Auth::user();
        
        $orders = $this->orderRepository->paginateForUserBranch(
            (int) $user->branch_id,
            $user->hasPermission('orders.approve_admin'),
            10
        );

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * 4. Handle Approvals (Update Status)
     */
    public function updateStatus(Request $request, $id)
    {
        $order = $this->orderRepository->findOrFail((int) $id);
        $action = $request->input('action'); // 'approve' or 'reject'

        if ($action == 'reject') {
            $order->update(['status' => 'rejected']);
            return back()->with('success', 'Order has been rejected.');
        }

        // Logic Chain
        // 1. User with admin approval permission approves -> goes to Finance
        if ($order->status == 'pending_admin' && Auth::user()->hasPermission('orders.approve_admin')) {
            $order->update([
                'status' => 'pending_finance',
                'admin_approved_at' => now()
            ]);
            return back()->with('success', 'Approved! Order forwarded to Finance.');
        } 
        
        // 2. User with finance approval permission approves -> Final Approved
        if ($order->status == 'pending_finance' && Auth::user()->hasPermission('orders.approve_finance')) {
            $order->update([
                'status' => 'approved',
                'finance_approved_at' => now()
            ]);
            return back()->with('success', 'Final Approval Granted! Order is ready to print.');
        }

        return back()->with('error', 'Unauthorized action or invalid status flow.');
    }

    /**
     * 5. Print/Export PDF
     */
    public function print($id)
    {
        $order = $this->orderRepository->findForPrint((int) $id);

        if ($order->status != 'approved') {
            abort(403, 'Order must be fully approved to print.');
        }

        // Return a print view
        return view('admin.orders.print', compact('order'));
    }
}
