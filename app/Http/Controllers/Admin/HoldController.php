<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Hold;
use App\Models\Inventory;
use App\Models\Product;
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
        $barangays = Barangay::orderBy('barangay_name')->get();

        $batches = Inventory::query()
            ->where('quantity', '>', 0)
            ->withSum([
                'holdItems as held_quantity' => function ($query) {
                    $query->whereHas('hold', function ($holdQuery) {
                        $holdQuery->whereIn('status', ['pending', 'approved']);
                    });
                },
            ], 'quantity')
            ->orderBy('expiry_date')
            ->get(['id', 'product_id', 'batch_number', 'quantity'])
            ->map(function ($batch) {
                $available = max(0, (int) $batch->quantity - (int) ($batch->held_quantity ?? 0));
                $batch->available_quantity = $available;

                return $batch;
            })
            ->filter(function ($batch) {
                return (int) $batch->available_quantity > 0;
            })
            ->values();

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

        $requestedByInventory = [];
        foreach ($validated['items'] as $item) {
            $inventoryId = (int) $item['inventory_id'];
            $requestedByInventory[$inventoryId] = ($requestedByInventory[$inventoryId] ?? 0) + (int) $item['quantity'];
        }

        $inventoryIds = array_keys($requestedByInventory);
        $inventories = Inventory::query()
            ->whereIn('id', $inventoryIds)
            ->withSum([
                'holdItems as held_quantity' => function ($query) {
                    $query->whereHas('hold', function ($holdQuery) {
                        $holdQuery->whereIn('status', ['pending', 'approved']);
                    });
                },
            ], 'quantity')
            ->get(['id', 'quantity'])
            ->keyBy('id');

        $errors = [];
        foreach ($requestedByInventory as $inventoryId => $requestedQty) {
            $inventory = $inventories->get($inventoryId);

            if (!$inventory) {
                $errors["items.{$inventoryId}.inventory_id"] = "Selected inventory #{$inventoryId} no longer exists.";
                continue;
            }

            $available = max(0, (int) $inventory->quantity - (int) ($inventory->held_quantity ?? 0));
            if ($requestedQty > $available) {
                $errors["items.{$inventoryId}.quantity"] = "Requested hold quantity ({$requestedQty}) exceeds available quantity ({$available}) for inventory #{$inventoryId}.";
            }
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

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
            'barangay',
            'creator',
            'approver',
            'items.product',
            'items.inventory',
            'items.inventory.branch',
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
