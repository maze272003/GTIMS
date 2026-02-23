<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\Branch;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class LowStockSettingController extends Controller
{
    public function __construct(private AnalyticsService $analyticsService) {}

    public function index(Request $request)
    {
        $alertBranchId = $request->integer('alert_branch_id')
            ?: $request->integer('branch_id');
        $alertProductId = $request->integer('alert_product_id');
        $alertSearch = trim((string) $request->input('alert_search', ''));

        $globalSetting = LowStockSetting::where('is_global', true)->first();

        // Branch defaults: product_id NULL + branch_id NOT NULL
        $branchDefaults = LowStockSetting::where('is_global', false)
            ->whereNull('product_id')
            ->whereNotNull('branch_id')
            ->with('branch')
            ->orderBy('branch_id')
            ->get();

        // Overrides: product_id NOT NULL (with or without branch)
        $overrides = LowStockSetting::where('is_global', false)
            ->whereNotNull('product_id')
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
        $branches = Branch::orderBy('id')->get();

        $globalThreshold = $globalSetting?->threshold ?? 100;

        // Low stock alerts (branch-aware), then additional server-side filtering.
        $lowStockItems = collect(
            $this->analyticsService->getLowStockAlerts($alertBranchId ?: null)
        );

        if ($alertProductId) {
            $lowStockItems = $lowStockItems->where('product_id', $alertProductId);
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
            'alertSearch'
        ));
    }

    public function updateGlobal(Request $request)
    {
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
            'branch_id' => 'required|exists:branches,id',
            'threshold' => 'required|integer|min:1',
        ]);

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
            'branch_id'  => 'nullable|exists:branches,id',
            'threshold'  => 'required|integer|min:1',
        ]);

        LowStockSetting::updateOrCreate(
            [
                'is_global'  => false,
                'product_id' => $validated['product_id'],
                'branch_id'  => $validated['branch_id'] ?? null, // null = all branches
            ],
            ['threshold' => $validated['threshold']]
        );

        return back()->with('success', 'Override saved.');
    }

    public function destroyOverride(LowStockSetting $setting)
    {
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
