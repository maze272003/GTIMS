<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // <--- Crucial for the Sum() function
use Illuminate\Support\Facades\Log;
use middleware;
use index;

class OrderController extends Controller
{
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
    $user = Auth::user();
    $currentBranchId = $user->branch_id;

    // 1. Fetch ALL active inventory grouped by Product and Branch
    // This gives us raw rows like: [Prod 1, Branch 1, Qty 50], [Prod 1, Branch 2, Qty 20]
    $rawInventory = Inventory::where('is_archived', 0)
        ->select('product_id', 'branch_id', DB::raw('SUM(quantity) as total_qty'))
        ->groupBy('product_id', 'branch_id')
        ->get();

    // 2. Build the Master StockMap
    // Structure: [ ProductID => ['rhu1' => 50, 'rhu2' => 20, 'total' => 70] ]
    $stockMap = [];
    foreach($rawInventory as $item) {
        $pid = $item->product_id;
        
        if (!isset($stockMap[$pid])) {
            $stockMap[$pid] = ['rhu1' => 0, 'rhu2' => 0, 'total' => 0];
        }

        // Assign quantity to specific branch keys
        // Assuming ID 1 is RHU 1 and ID 2 is RHU 2
        if ($item->branch_id == 1) {
            $stockMap[$pid]['rhu1'] = (int)$item->total_qty;
        } elseif ($item->branch_id == 2) {
            $stockMap[$pid]['rhu2'] = (int)$item->total_qty;
        }

        // Add to total
        $stockMap[$pid]['total'] += (int)$item->total_qty;
    }

    // 3. Generate Suggested Items (Low Stock logic)
    // We only suggest items that are low in the CURRENT user's branch
    $products = Product::where('is_archived', 0)->orderBy('generic_name')->get();
    $suggestedItems = [];

    foreach($products as $product) {
        $stats = $stockMap[$product->id] ?? ['rhu1' => 0, 'rhu2' => 0, 'total' => 0];
        
        // Determine stock for the user's specific branch
        $myBranchStock = ($currentBranchId == 1) ? $stats['rhu1'] : $stats['rhu2'];

        if ($myBranchStock <= 100) {
            $suggestedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->generic_name . ' (' . $product->brand_name . ')',
                'rhu1_stock' => $stats['rhu1'],
                'rhu2_stock' => $stats['rhu2'],
                'total_stock' => $stats['total'],
                'suggested_qty' => 1000 - $myBranchStock
            ];
        }
    }

    return view('admin.orders.create', [
        'suggestedItems' => $suggestedItems,
        'allProducts' => $products,
        'stockMap' => $stockMap
    ]);
}
    /**
     * 2. Store the Order (Pharmacist/Admin Action)
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Create the Order Header
                $order = Order::create([
                    'branch_id' => Auth::user()->branch_id,
                    'user_id' => Auth::id(),
                    'status' => 'pending_admin', // Initial status
                    'remarks' => $request->remarks
                ]);

                // Create Order Items
                foreach ($request->items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity_requested' => $item['quantity'],
                    ]);
                }
            });

            return redirect()->route('admin.orders.index')
                ->with('success', 'Order submitted successfully! Waiting for Admin approval.');

        } catch (\Exception $e) {
            Log::error('Order Store Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to submit order. Please try again.');
        }
    }

    /**
     * 3. List Orders (Index)
     */
    public function index()
    {
                $this->checkAccess(); // <--- Security Check

        $user = Auth::user();
        
        $query = Order::with(['branch', 'user', 'items.product']);

        // Branch-scoped users see only their branch orders
        if ($user->branch_id && !$user->hasPermission('orders.approve_admin')) {
            $query->where('branch_id', $user->branch_id);
        }
        // Users with approve_admin permission see all

        $orders = $query->latest()->paginate(10);

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * 4. Handle Approvals (Update Status)
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $userLevel = Auth::user()->user_level_id;
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
        $order = Order::with(['items.product', 'branch', 'user'])->findOrFail($id);

        if ($order->status != 'approved') {
            abort(403, 'Order must be fully approved to print.');
        }

        // Return a print view
        return view('admin.orders.print', compact('order'));
    }
}