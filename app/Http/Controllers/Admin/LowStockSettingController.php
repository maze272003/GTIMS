<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Inventory;
use App\Services\AnalyticsService;
use App\Services\BranchAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class LowStockSettingController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private BranchAccessService $branchAccessService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $accessibleBranchIds = $this->branchAccessService->accessibleBranchIds($user);
        $alertBranchId = $this->branchAccessService->resolveBranchFilter(
            $user,
            $request->integer('alert_branch_id') ?: $request->integer('branch_id'),
            defaultToUserBranch: true
        );
        $alertProductId = $request->integer('alert_product_id');
        $alertBatchId = $request->integer('alert_batch_id');
        $alertSearch = trim((string) $request->input('alert_search', ''));

        $globalSetting = LowStockSetting::where('is_global', true)->first();

        // Branch defaults: product_id NULL + branch_id NOT NULL
        $branchDefaults = LowStockSetting::where('is_global', false)
            ->whereNull('product_id')
            ->whereNotNull('branch_id')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->with('branch')
            ->orderBy('branch_id')
            ->get();

        // Overrides: product_id NOT NULL (with or without branch)
        $overrides = LowStockSetting::where('is_global', false)
            ->whereNotNull('product_id')
            ->when(
                !$this->branchAccessService->canAccessAllBranches($user),
                fn ($query) => $query->where(function ($nestedQuery) use ($accessibleBranchIds) {
                    $nestedQuery->whereNull('branch_id')
                        ->orWhereIn('branch_id', $accessibleBranchIds);
                })
            )
            ->with(['product', 'branch'])
            ->orderByDesc('updated_at')
            ->paginate(20, ['*'], 'overrides_page')
            ->withQueryString();

        // Products (your table doesn't have "name", so use generic_name)
        // Safe ordering even if generic_name is NULL
        $products = Product::where('is_archived', false)
            ->orderByRaw('generic_name IS NULL, generic_name ASC')
            ->get();

        // Branches: avoid unknown column errors (don’t assume "name" exists)
        $branches = $this->branchAccessService->visibleBranches($user);

        $globalThreshold = $globalSetting?->threshold ?? 100;

        // Low stock alerts (branch-aware), then additional server-side filtering.
        $lowStockItems = collect(
            $this->analyticsService->getLowStockAlerts($alertBranchId ?: null)
        );

        if ($alertProductId) {
            $lowStockItems = $lowStockItems->where('product_id', $alertProductId);
        }

        if ($alertBatchId) {
            $lowStockItems = $lowStockItems->where('inventory_id', $alertBatchId);
        }

        if ($alertSearch !== '') {
            $search = mb_strtolower($alertSearch);
            $lowStockItems = $lowStockItems->filter(function (array $item) use ($search) {
                $productName = mb_strtolower((string) ($item['product_name'] ?? ''));
                $batchNumber = mb_strtolower((string) ($item['batch_number'] ?? ''));
                $branchName = mb_strtolower((string) ($item['branch_name'] ?? ''));

                return str_contains($productName, $search)
                    || str_contains($batchNumber, $search)
                    || str_contains($branchName, $search);
            });
        }

        $lowStockItems = $this->paginateAlerts($lowStockItems, $request);

        return view('admin.settings.low-stock', compact(
            'globalSetting',
            'globalThreshold',
            'branchDefaults',
            'overrides',
            'products',
            'branches',
            'lowStockItems',
            'alertBranchId',
            'alertProductId',
            'alertBatchId',
            'alertSearch'
        ));
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'product_id' => 'nullable|exists:products,id',
        ]);

        $branchId = $this->branchAccessService->resolveBranchFilter(
            $request->user(),
            $validated['branch_id'] ?? null,
            defaultToUserBranch: true
        );
        $productId = isset($validated['product_id']) ? (int) $validated['product_id'] : null;
        $availableExpr = 'COALESCE(onhand_qty, quantity) - COALESCE(hold_qty, 0)';

        $inventoryBase = Inventory::query()
            ->where('is_archived', false)
            ->whereRaw("{$availableExpr} > 0")
            ->whereHas('product', fn ($q) => $q->where('is_archived', false))
            ->whereHas('branch', fn ($q) => $q->where('is_archived', false))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $productIds = (clone $inventoryBase)
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_archived', false)
            ->orderByRaw('generic_name IS NULL, generic_name ASC')
            ->orderBy('brand_name')
            ->get(['id', 'generic_name', 'brand_name'])
            ->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'generic_name' => $product->generic_name,
                'brand_name' => $product->brand_name,
                'label' => trim(($product->generic_name ?? '') . ' ' . ($product->brand_name ?? '')),
            ])
            ->values();

        $batches = collect();
        if ($productId) {
            $batches = (clone $inventoryBase)
                ->where('product_id', $productId)
                ->orderBy('expiry_date')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'product_id', 'branch_id', 'batch_number', 'expiry_date'])
                ->map(fn (Inventory $inventory) => [
                    'id' => (int) $inventory->id,
                    'product_id' => (int) $inventory->product_id,
                    'branch_id' => (int) $inventory->branch_id,
                    'batch_number' => $inventory->batch_number,
                    'expiry_date' => optional($inventory->expiry_date)->toDateString(),
                ])
                ->values();
        }

        return response()->json([
            'products' => $products,
            'batches' => $batches,
        ]);
    }

    public function updateGlobal(Request $request)
    {
        $this->branchAccessService->authorizeGlobalBranchAccess($request->user(), 'update global low stock settings');

        $validated = $request->validate([
            'threshold' => 'required|integer|min:1',
        ]);

        LowStockSetting::updateOrCreate(
            ['is_global' => true],
            [
                'threshold' => $validated['threshold'],
                'product_id' => null,
                'branch_id' => null
            ]
        );

        return back()->with('success', 'Global threshold updated.');
    }

    public function storeBranchDefault(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'threshold' => 'required|integer|min:1',
        ]);

        $validated['branch_id'] = $this->branchAccessService->resolveBranchFilter($request->user(), $validated['branch_id']);

        LowStockSetting::updateOrCreate(
            [
                'is_global' => false,
                'product_id' => null,
                'branch_id' => $validated['branch_id']
            ],
            ['threshold' => $validated['threshold']]
        );

        return back()->with('success', 'Branch default threshold saved.');
    }

    public function storeOverride(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'branch_id'  => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'threshold'  => 'required|integer|min:1',
        ]);

        if (empty($validated['branch_id'])) {
            if ($this->branchAccessService->canAccessAllBranches($request->user())) {
                $validated['branch_id'] = null;
            } else {
                $validated['branch_id'] = $this->branchAccessService->resolveBranchFilter($request->user(), null);
            }
        } else {
            $validated['branch_id'] = $this->branchAccessService->resolveBranchFilter($request->user(), $validated['branch_id']);
        }

        LowStockSetting::updateOrCreate(
            [
                'is_global'  => false,
                'product_id' => $validated['product_id'],
                'branch_id'  => $validated['branch_id'] ?? null,
            ],
            ['threshold' => $validated['threshold']]
        );

        return back()->with('success', 'Override saved.');
    }

    public function destroyOverride(LowStockSetting $setting)
    {
        if (is_null($setting->branch_id)) {
            $this->branchAccessService->authorizeGlobalBranchAccess(request()->user(), 'remove an all-branch low stock override');
        } else {
            $this->branchAccessService->authorizeBranchAccess(request()->user(), $setting->branch_id, 'remove another branch\'s low stock override');
        }

        if ($setting->is_global) {
            return back()->with('error', 'Cannot delete global setting.');
        }

        $setting->delete();

        return back()->with('success', 'Setting removed.');
    }

    private function paginateAlerts($alerts, Request $request): LengthAwarePaginator
    {
        $alerts = collect($alerts)->values();
        $perPage = 15;
        $pageName = 'alerts_page';
        $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
        $sliced = $alerts->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $sliced,
            $alerts->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ]
        );

        return $paginator->appends($request->except($pageName));
    }
}
