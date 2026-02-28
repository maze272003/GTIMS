<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemAnalyticsController extends Controller
{
    protected SystemAnalyticsService $service;

    public function __construct(SystemAnalyticsService $service)
    {
        $this->service = $service;
    }

    /**
     * Validate and extract common date/filter parameters from the request.
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'branch_id' => 'nullable|integer',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        return [
            'from' => isset($validated['from']) ? Carbon::parse($validated['from']) : null,
            'to' => isset($validated['to']) ? Carbon::parse($validated['to']) : null,
            'branch_id' => $validated['branch_id'] ?? null,
            'group_by' => $validated['group_by'] ?? 'day',
        ];
    }

    public function overview(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer']);

        return response()->json(
            $this->service->getSystemOverview($request->integer('branch_id') ?: null)
        );
    }

    public function inventoryMovementTrends(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json(
            $this->service->getInventoryMovementTrends(
                $filters['from'],
                $filters['to'],
                $filters['branch_id'],
                $filters['group_by']
            )
        );
    }

    public function stockLevelDistribution(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer']);

        return response()->json([
            'distribution' => $this->service->getStockLevelDistribution(
                $request->integer('branch_id') ?: null
            ),
        ]);
    }

    public function expiryTracking(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer']);

        return response()->json(
            $this->service->getExpiryTracking(
                $request->integer('branch_id') ?: null
            )
        );
    }

    public function requestStatusDistribution(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer']);

        return response()->json(
            $this->service->getRequestStatusDistribution(
                $request->integer('branch_id') ?: null
            )
        );
    }

    public function requestVolumeTrends(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json(
            $this->service->getRequestVolumeTrends(
                $filters['from'],
                $filters['to'],
                $filters['branch_id'],
                $filters['group_by']
            )
        );
    }

    public function holdAnalytics(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer']);

        return response()->json(
            $this->service->getHoldAnalytics(
                $request->integer('branch_id') ?: null
            )
        );
    }

    public function userActivityTrends(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json(
            $this->service->getUserActivityTrends(
                $filters['from'],
                $filters['to'],
                $filters['group_by']
            )
        );
    }

    public function auditEventDistribution(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json(
            $this->service->getAuditEventDistribution(
                $filters['from'],
                $filters['to']
            )
        );
    }

    public function inventoryTurnover(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json(
            $this->service->getInventoryTurnover(
                $filters['from'],
                $filters['to'],
                $filters['branch_id']
            )
        );
    }
}
