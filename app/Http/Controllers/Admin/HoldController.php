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
use App\Services\BranchAccessService;
use App\Services\HoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HoldController extends Controller
{
    public function __construct(
        protected HoldService $holdService,
        protected HoldRepositoryInterface $holdRepository,
        protected ProductRepositoryInterface $productRepository,
        protected BranchAccessService $branchAccessService
    ) {
    }

    public function index(Request $request)
    {
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $request->branch_id, defaultToUserBranch: true);

        $holds = $this->holdRepository->paginateWithFilters(
            $request->status,
            $request->type,
            $branchId,
            20
        );

        $branches = $this->branchAccessService->visibleBranches($request->user());

        return view('admin.holds.index', compact('holds', 'branches'));
    }

    public function create()
    {
        $products = $this->productRepository->getActive();
        $branches = $this->branchAccessService->visibleBranches(Auth::user());
        $barangays = Barangay::orderBy('barangay_name')->get();
        $batches = $this->holdRepository->getAvailableBatches($this->branchAccessService->accessibleBranchIds(Auth::user()));

        return view('admin.holds.create', compact('products', 'branches', 'barangays', 'batches'));
    }

    public function store(StoreHoldRequest $request)
    {
        $validated = $request->validated();
        $validated['branch_id'] = $this->branchAccessService->resolveBranchFilter($request->user(), $validated['branch_id']);

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
        $this->branchAccessService->authorizeBranchAccess(Auth::user(), $hold->branch_id, 'view holds from another branch');
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
        $this->branchAccessService->authorizeBranchAccess(Auth::user(), $hold->branch_id, 'approve holds from another branch');

        if ($hold->status !== 'pending') {
            return back()->with('error', 'Hold can only be approved when pending.');
        }

        $this->holdService->approveHold($hold, Auth::id());

        return back()->with('success', 'Hold approved successfully.');
    }

    public function release(Request $request, Hold $hold)
    {
        $this->branchAccessService->authorizeBranchAccess($request->user(), $hold->branch_id, 'release holds from another branch');

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
        $this->branchAccessService->authorizeBranchAccess($request->user(), $hold->branch_id, 'cancel holds from another branch');

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

        $inventory = Inventory::query()->findOrFail((int) $validated['inventory_id']);
        $this->branchAccessService->authorizeBranchAccess($request->user(), $inventory->branch_id, 'pull out stock from another branch');

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
