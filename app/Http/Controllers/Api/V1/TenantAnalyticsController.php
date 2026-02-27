<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
    ) {
    }

    public function sla(Request $request): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $data = $this->analyticsService->getRequestSLAMetrics(
            $request->date('from'),
            $request->date('to'),
            $tenantContext
        );

        return response()->json(['data' => $data]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $data = $this->analyticsService->getReorderSuggestions(
            $request->integer('branch_id') ?: null,
            $tenantContext
        );

        return response()->json([
            'data' => $data,
            'empty' => empty($data),
            'message' => empty($data) ? 'No reorder suggestions yet for this tenant scope.' : null,
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $data = $this->analyticsService->getLowStockAlerts(
            $request->integer('branch_id') ?: null,
            $tenantContext
        );

        return response()->json([
            'data' => $data,
            'empty' => empty($data),
            'message' => empty($data) ? 'No low-stock alerts in this tenant scope.' : null,
        ]);
    }

    public function kpis(Request $request): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $data = $this->analyticsService->getStockKPIs(
            $request->integer('branch_id') ?: null,
            $tenantContext
        );

        return response()->json(['data' => $data]);
    }

    protected function tenantContext(Request $request): ?TenantContext
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        return $tenantContext;
    }
}

