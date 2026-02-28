<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHoldRequest;
use App\Models\Branch;
use App\Models\Barangay;
use App\Models\Hold;
use App\Models\Inventory;
use App\Repositories\Interfaces\HoldRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Services\HoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HoldController extends Controller
{
    public function __construct(
        protected HoldService $holdService,
        protected HoldRepositoryInterface $holdRepository,
        protected ProductRepositoryInterface $productRepository
    ) {
    }

    public function index(Request $request)
    {
        $holds = $this->holdRepository->paginateWithFilters(
            $request->status,
            $request->type,
            $request->branch_id ? (int) $request->branch_id : null,
            20
        );

        $branches = Branch::query()->active()->orderBy('name')->get();

        return view('admin.holds.index', compact('holds', 'branches'));
    }

    public function create()
    {
        $products = $this->productRepository->getActive();
        $branches = Branch::query()->active()->orderBy('name')->get();
        $barangays = Barangay::orderBy('barangay_name')->get();
        $batches = $this->holdRepository->getAvailableBatches();

        return view('admin.holds.create', compact('products', 'branches', 'barangays', 'batches'));
    }

    public function store(StoreHoldRequest $request)
    {
        $validated = $request->validated();

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
