<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderAdminService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected BranchAccessService $branchAccessService
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
    $currentBranchId = $this->branchAccessService->branchId($user) ?? 0;
    $visibleBranchIds = $this->branchAccessService->accessibleBranchIds($user);

    // 1. Fetch ALL active inventory grouped by Product and Branch.
    $rawInventory = $this->orderRepository->getGroupedActiveInventoryTotals()
        ->filter(fn ($item) => in_array((int) $item->branch_id, $visibleBranchIds, true))
        ->values();

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
        'branches' => $this->branchAccessService->visibleBranches($user),
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

        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $validated['branch_id']);
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

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $requestingBranchId = $this->branchAccessService->branchId(Auth::user());
        abort_if(!$requestingBranchId, 403, app(AuthSessionService::class)->getForbiddenMessage(Auth::user(), 'create orders without an assigned branch'));

        try {
            $this->orderRepository->createOrderWithItems(
                $requestingBranchId,
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
            $this->branchAccessService->branchId($user) ?? 0,
            $this->branchAccessService->canAccessAllBranches($user),
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
        $this->branchAccessService->authorizeBranchAccess($request->user(), $order->branch_id, 'update orders from another branch');
        $action = $request->input('action'); // 'approve' or 'reject'

        try {
            return DB::transaction(function () use ($order, $action) {
                if ($action == 'reject') {
                    $order->update(['status' => 'rejected']);
                    Log::info('Order rejected', ['order_id' => $order->id, 'user_id' => Auth::id()]);
                    return back()->with('success', 'Order has been rejected.');
                }

                // Logic Chain
                // 1. User with admin approval permission approves -> goes to Finance
                if ($order->status == 'pending_admin' && Auth::user()->hasPermission('orders.approve_admin')) {
                    $order->update([
                        'status' => 'pending_finance',
                        'admin_approved_at' => now()
                    ]);
                    Log::info('Order approved by admin', ['order_id' => $order->id, 'user_id' => Auth::id()]);
                    return back()->with('success', 'Approved! Order forwarded to Finance.');
                }

                // 2. User with finance approval permission approves -> Final Approved
                if ($order->status == 'pending_finance' && Auth::user()->hasPermission('orders.approve_finance')) {
                    $order->update([
                        'status' => 'approved',
                        'finance_approved_at' => now()
                    ]);
                    Log::info('Order approved by finance', ['order_id' => $order->id, 'user_id' => Auth::id()]);
                    return back()->with('success', 'Final Approval Granted! Order is ready to print.');
                }

                return back()->with('error', 'Unauthorized action or invalid status flow.');
            });
        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to update order status. Please try again.');
        }
    }

    /**
     * 5. Print/Export PDF
     */
    public function print($id)
    {
        $order = $this->orderRepository->findForPrint((int) $id);
        $this->branchAccessService->authorizeBranchAccess(Auth::user(), $order->branch_id, 'print orders from another branch');

        if ($order->status != 'approved') {
            abort(403, 'Order must be fully approved to print.');
        }

        // Return a print view
        return view('admin.orders.print', compact('order'));
    }

    public function requestIndex()
    {
        $this->checkAccess();

        $user = Auth::user();
        $orders = $this->orderRepository->paginateApprovedForReceiving(
            $this->branchAccessService->branchId($user) ?? 0,
            $this->branchAccessService->canAccessAllBranches($user),
            10
        );

        return view('admin.requests.index', compact('orders'));
    }

    public function requestShow(int $id)
    {
        $this->checkAccess();

        $order = $this->orderRepository->findForReceiving($id);
        $this->branchAccessService->authorizeBranchAccess(Auth::user(), $order->branch_id, 'view order receiving details from another branch');

        if ($order->status !== 'approved') {
            abort(404);
        }

        return view('admin.requests.show', compact('order'));
    }

    public function receive(Request $request, int $id)
    {
        $this->checkAccess();

        $order = $this->orderRepository->findForReceiving($id);
        $this->branchAccessService->authorizeBranchAccess($request->user(), $order->branch_id, 'receive orders for another branch');

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.batches' => 'required|array|min:1',
            'items.*.batches.*.batch_number' => 'required|string|max:120',
            'items.*.batches.*.quantity' => 'required|integer|min:1',
            'items.*.batches.*.expiry_date' => 'required|date',
        ]);

        try {
            $this->orderRepository->receiveApprovedOrder($order->id, (int) Auth::id(), $validated['items']);

            return redirect()
                ->route('admin.requests.show', $order->id)
                ->with('success', 'Order received and stocked into inventory.');
        } catch (\Exception $e) {
            Log::error('Order receive error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'Failed to receive order.');
        }
    }
}
