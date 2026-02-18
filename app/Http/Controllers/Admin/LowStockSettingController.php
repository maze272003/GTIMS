<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\ReorderRule;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

class LowStockSettingController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function index()
    {
        $globalSetting = LowStockSetting::where('is_global', true)->first();
        $overrides = LowStockSetting::where('is_global', false)
            ->with(['product', 'branch'])
            ->paginate(20);
        $products = Product::where('is_archived', false)->get();
        $branches = Branch::all();
        $lowStockAlerts = $this->analyticsService->getLowStockAlerts();

        return view('admin.settings.low-stock', compact('globalSetting', 'overrides', 'products', 'branches', 'lowStockAlerts'));
    }

    public function updateGlobal(Request $request)
    {
        $validated = $request->validate([
            'threshold' => 'required|integer|min:1',
        ]);

        LowStockSetting::updateOrCreate(
            ['is_global' => true],
            ['threshold' => $validated['threshold'], 'product_id' => null, 'branch_id' => null]
        );

        return back()->with('success', 'Global threshold updated.');
    }

    public function storeOverride(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'nullable|exists:branches,id',
            'threshold' => 'required|integer|min:1',
        ]);

        LowStockSetting::updateOrCreate(
            ['product_id' => $validated['product_id'], 'branch_id' => $validated['branch_id'] ?? null],
            ['threshold' => $validated['threshold'], 'is_global' => false]
        );

        return back()->with('success', 'Override saved.');
    }

    public function destroyOverride(LowStockSetting $setting)
    {
        if ($setting->is_global) {
            return back()->with('error', 'Cannot delete global setting.');
        }
        $setting->delete();
        return back()->with('success', 'Override removed.');
    }
}
