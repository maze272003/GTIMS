<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHoldRequest;
use App\Models\Branch;
use App\Models\Barangay;
use App\Models\Hold;
use App\Repositories\Interfaces\HoldRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Services\HoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

        try {
            $this->holdService->createHold(
                collect($validated)->only(['barangay_id', 'branch_id', 'type', 'reason_code', 'remarks', 'expires_at'])->toArray(),
                $validated['items'],
                Auth::id()
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

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

        try {
            $this->holdService->releaseHold($hold, Auth::id(), $validated['reason'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Hold released successfully.');
    }

    public function cancel(Request $request, Hold $hold)
    {
        if (!in_array($hold->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'Hold can only be cancelled when pending or approved.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->holdService->cancelHold($hold, Auth::id(), $validated['reason'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Hold cancelled successfully.');
    }

    public function pullOut(Request $request)
    {
        $validated = $request->validate([
            'inventory_id' => 'required|integer|exists:inventories,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
            'reference_no' => 'nullable|string|max:100',
            'override_held' => 'nullable|boolean',
        ]);

        try {
            $this->holdService->pullOutInventory(
                inventoryId: (int) $validated['inventory_id'],
                quantity: (int) $validated['quantity'],
                userId: Auth::id(),
                reason: $validated['reason'] ?? null,
                referenceNo: $validated['reference_no'] ?? null,
                overrideHeld: (bool) ($validated['override_held'] ?? false)
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Pull-out processed successfully.');
    }
}
