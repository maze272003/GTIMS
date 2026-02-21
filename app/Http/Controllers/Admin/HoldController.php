<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Branch;
use App\Services\HoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HoldController extends Controller
{
    protected HoldService $holdService;

    public function __construct(HoldService $holdService)
    {
        $this->holdService = $holdService;
    }

    public function index(Request $request)
    {
        $holds = Hold::query()
            ->with([
                'branch',
                'creator',
                'approver',
                'items.product',
            ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->latest()
            ->paginate(20);

        $branches = Branch::all();

        return view('admin.holds.index', compact('holds', 'branches'));
    }

    public function create()
    {
        $products = Product::where('is_archived', false)->get();
        $branches = Branch::all();
        $barangays = \App\Models\Barangay::orderBy('barangay_name')->get();

        $batches = Inventory::query()
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->get(['id', 'product_id', 'batch_number', 'quantity']);

        return view('admin.holds.create', compact('products', 'branches', 'barangays', 'batches'));
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'barangay_id' => 'nullable|exists:barangays,id',
            'type' => 'required|in:reservation,quarantine,recall',
            'reason_code' => 'required|string|max:255',
            'remarks' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.inventory_id' => 'required|exists:inventories,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $this->holdService->createHold(
            collect($validated)->only(['barangay_id', 'branch_id', 'type', 'reason_code', 'remarks', 'expires_at'])->toArray(),
            $validated['items'],
            Auth::id()
        );

        return redirect()
            ->route('admin.holds.index')
            ->with('success', 'Hold created successfully.');
    }

    public function show(Hold $hold)
    {
        $hold->load([
            'branch',
            'creator',
            'approver',
            'barangay', // if you want to show barangay details in the hold view

            // ✅ for table + tooltip
            'items.product',
            'items.inventory',
            'items.inventory.branch', // requires Inventory::branch() relation

            // ✅ your blade uses $history->user
            'statusHistory.changer',
        ]);

        return view('admin.holds.show', compact('hold'));
    }

    public function approve(Hold $hold)
    {
        if ($hold->status !== 'pending') {
            return back()->with('error', 'Hold can only be approved when pending.');
        }

        $this->holdService->approveHold($hold, Auth::id());

        return back()->with('success', 'Hold approved successfully.');
    }

    public function release(Request $request, Hold $hold)
    {
        if (!in_array($hold->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'Hold can only be released when pending or approved.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->holdService->releaseHold($hold, Auth::id(), $validated['reason'] ?? null);

        return back()->with('success', 'Hold released successfully.');
    }
}
