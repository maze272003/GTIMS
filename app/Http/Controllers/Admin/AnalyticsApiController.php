<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsApiController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function slaMetrics(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;

        return response()->json($this->analyticsService->getRequestSLAMetrics($from, $to));
    }

    public function reorderSuggestions(Request $request)
    {
        return response()->json([
            'suggestions' => $this->analyticsService->getReorderSuggestions($request->branch_id),
        ]);
    }

    public function lowStockAlerts(Request $request)
    {
        return response()->json([
            'alerts' => $this->analyticsService->getLowStockAlerts($request->branch_id),
        ]);
    }

    public function stockKPIs(Request $request)
    {
        return response()->json($this->analyticsService->getStockKPIs($request->branch_id));
    }
}
