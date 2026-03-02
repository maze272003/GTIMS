<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LowStockSettingController extends Controller
{
    public function __construct(private AnalyticsService $analyticsService) {}

    public function index(Request $request)
    {
        [
            'alertBranchId' => $alertBranchId,
            'alertProductId' => $alertProductId,
            'alertBatchId' => $alertBatchId,
            'alertSearch' => $alertSearch,
        ] = $this->validatedAlertFilters($request);

        $globalSetting = LowStockSetting::query()->where('is_global', true)->first();

        // Branch defaults: product_id NULL + branch_id NOT NULL
        $branchDefaults = LowStockSetting::query()
            ->where('is_global', false)
            ->whereNull('product_id')
            ->whereNotNull('branch_id')
            ->with('branch')
            ->orderBy('branch_id')
            ->get();

        // Overrides: product_id NOT NULL (with or without branch)
        $overrides = LowStockSetting::query()
            ->where('is_global', false)
            ->whereNotNull('product_id')
            ->with(['product', 'branch'])
            ->orderByDesc('updated_at')
            ->paginate(20, ['*'], 'overrides_page')
            ->withQueryString();

        // Override form products stay system-wide.
        $products = Product::query()
            ->where('is_archived', false)
            ->orderByRaw('generic_name IS NULL, generic_name ASC')
            ->get();

        // Filter products depend on selected branch.
        $alertProducts = $this->getAlertProducts($alertBranchId);
        $alertBatches = $this->getAlertBatches($alertBranchId, $alertProductId);

        $branches = Branch::query()
            ->active()
            ->orderBy('name')
            ->get();

        $globalThreshold = (int) ($globalSetting?->threshold ?? 100);

        $lowStockItems = collect(
            $this->analyticsService->getLowStockAlerts(
                $alertBranchId ?: null,
                $alertProductId ?: null,
                $alertBatchId ?: null,
            )
        );

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
            'alertSearch',
            'alertProducts',
            'alertBatches',
        ));
    }

    public function filterOptions(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ])->validate();

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $productId = isset($validated['product_id']) ? (int) $validated['product_id'] : null;

        return response()->json([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'products' => $this->getAlertProducts($branchId)
                ->map(fn (Product $product) => [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->generic_name ?? $product->name ?? "Product #{$product->id}"),
                ])
                ->values()
                ->all(),
            'batches' => $this->getAlertBatches($branchId, $productId)->values()->all(),
        ]);
    }

    public function updateGlobal(Request $request)
    {
        $validated = $request->validate([
            'threshold' => 'required|integer|min:1',
        ]);

        LowStockSetting::query()->updateOrCreate(
            ['is_global' => true],
            [
                'threshold' => $validated['threshold'],
                'product_id' => null,
                'branch_id' => null,
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

        LowStockSetting::query()->updateOrCreate(
            [
                'is_global' => false,
                'product_id' => null,
                'branch_id' => $validated['branch_id'],
            ],
            ['threshold' => $validated['threshold']]
        );

        return back()->with('success', 'Branch default threshold saved.');
    }

    public function storeOverride(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'threshold' => 'required|integer|min:1',
        ]);

        LowStockSetting::query()->updateOrCreate(
            [
                'is_global' => false,
                'product_id' => $validated['product_id'],
                'branch_id' => $validated['branch_id'] ?? null,
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

    private function validatedAlertFilters(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'alert_search' => ['nullable', 'string', 'max:120'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'alert_branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'alert_product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'alert_batch_id' => [
                'nullable',
                'integer',
                Rule::exists('inventories', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ]);

        $validator->after(function ($validator) use ($request) {
            $branchId = $request->integer('alert_branch_id') ?: $request->integer('branch_id');
            $productId = $request->integer('alert_product_id');
            $batchId = $request->integer('alert_batch_id');

            if (!$batchId) {
                return;
            }

            if (!$productId) {
                $validator->errors()->add('alert_product_id', 'Select a product before filtering by batch.');
                return;
            }

            $batchExists = Inventory::query()
                ->where('id', $batchId)
                ->where('is_archived', false)
                ->whereHas('product', fn ($query) => $query->where('is_archived', false))
                ->whereHas('branch', fn ($query) => $query->where('is_archived', false))
                ->where('product_id', $productId)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->exists();

            if (!$batchExists) {
                $validator->errors()->add('alert_batch_id', 'Selected batch does not match the chosen branch/product filter.');
            }
        });

        $validated = $validator->validate();

        $alertBranchId = isset($validated['alert_branch_id'])
            ? (int) $validated['alert_branch_id']
            : (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null);
        $alertProductId = isset($validated['alert_product_id']) ? (int) $validated['alert_product_id'] : null;
        $alertBatchId = isset($validated['alert_batch_id']) ? (int) $validated['alert_batch_id'] : null;

        return [
            'alertBranchId' => $alertBranchId,
            'alertProductId' => $alertProductId,
            'alertBatchId' => $alertBatchId,
            'alertSearch' => trim((string) ($validated['alert_search'] ?? '')),
        ];
    }

    private function getAlertProducts(?int $branchId): Collection
    {
        $query = Product::query()->where('is_archived', false);

        if ($branchId) {
            $query->whereHas('inventories', function ($inventoryQuery) use ($branchId) {
                $inventoryQuery
                    ->where('is_archived', false)
                    ->where('branch_id', $branchId)
                    ->whereHas('branch', fn ($branchQuery) => $branchQuery->where('is_archived', false));
            });
        }

        return $query
            ->orderByRaw('generic_name IS NULL, generic_name ASC')
            ->get();
    }

    private function getAlertBatches(?int $branchId, ?int $productId): Collection
    {
        if (!$productId) {
            return collect();
        }

        $availableExpr = '(COALESCE(onhand_qty, quantity) - COALESCE(hold_qty, 0))';
        $batches = Inventory::query()
            ->where('is_archived', false)
            ->where('product_id', $productId)
            ->whereHas('product', fn ($query) => $query->where('is_archived', false))
            ->whereHas('branch', fn ($query) => $query->where('is_archived', false))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereRaw("{$availableExpr} > 0")
            ->with('branch:id,name')
            ->select([
                'id',
                'branch_id',
                'batch_number',
                'expiry_date',
                'created_at',
                \DB::raw("{$availableExpr} as available_qty"),
            ])
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiry_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $batches->map(function (Inventory $inventory) use ($branchId) {
            $expiry = optional($inventory->expiry_date)->format('Y-m-d') ?? '-';
            $received = optional($inventory->created_at)->format('Y-m-d') ?? '-';
            $available = (int) ($inventory->available_qty ?? 0);
            $branchPrefix = $branchId ? '' : (($inventory->branch?->name ? "{$inventory->branch->name} - " : ''));
            $batchNumber = (string) ($inventory->batch_number ?: 'N/A');

            return [
                'id' => (int) $inventory->id,
                'batch_number' => $batchNumber,
                'branch_id' => (int) $inventory->branch_id,
                'branch_name' => (string) ($inventory->branch?->name ?? ''),
                'available_quantity' => $available,
                'expiry_date' => $expiry,
                'received_date' => $received,
                'label' => "{$branchPrefix}Batch #{$batchNumber} - Exp: {$expiry} - Avail: {$available} - Recv: {$received}",
            ];
        });
    }
}

