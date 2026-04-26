<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\BranchAccessService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsApiController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(
        AnalyticsService $analyticsService,
        protected BranchAccessService $branchAccessService
    ) {
        $this->analyticsService = $analyticsService;
    }

    public function slaMetrics(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $request->input('branch_id'), defaultToUserBranch: true);

        return response()->json($this->analyticsService->getRequestSLAMetrics($from, $to, $branchId));
    }

    public function reorderSuggestions(Request $request)
    {
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $request->input('branch_id'), defaultToUserBranch: true);

        return response()->json([
            'suggestions' => $this->analyticsService->getReorderSuggestions($branchId),
        ]);
    }

    public function lowStockAlerts(Request $request)
    {
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $request->input('branch_id'), defaultToUserBranch: true);

        return response()->json([
            'alerts' => $this->analyticsService->getLowStockAlerts($branchId),
        ]);
    }

    public function stockKPIs(Request $request)
    {
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $request->input('branch_id'), defaultToUserBranch: true);

        return response()->json($this->analyticsService->getStockKPIs($branchId));
    }
}
