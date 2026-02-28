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

    public function overview(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getSystemOverview($request->integer('branch_id') ?: null)
        );
    }

    public function inventoryMovementTrends(Request $request): JsonResponse
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;

        return response()->json(
            $this->service->getInventoryMovementTrends(
                $from,
                $to,
                $request->integer('branch_id') ?: null,
                $request->input('group_by', 'day')
            )
        );
    }

    public function stockLevelDistribution(Request $request): JsonResponse
    {
        return response()->json([
            'distribution' => $this->service->getStockLevelDistribution(
                $request->integer('branch_id') ?: null
            ),
        ]);
    }

    public function expiryTracking(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getExpiryTracking(
                $request->integer('branch_id') ?: null
            )
        );
    }

    public function requestStatusDistribution(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getRequestStatusDistribution(
                $request->integer('branch_id') ?: null
            )
        );
    }

    public function requestVolumeTrends(Request $request): JsonResponse
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;

        return response()->json(
            $this->service->getRequestVolumeTrends(
                $from,
                $to,
                $request->integer('branch_id') ?: null,
                $request->input('group_by', 'day')
            )
        );
    }

    public function holdAnalytics(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getHoldAnalytics(
                $request->integer('branch_id') ?: null
            )
        );
    }

    public function userActivityTrends(Request $request): JsonResponse
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;

        return response()->json(
            $this->service->getUserActivityTrends(
                $from,
                $to,
                $request->input('group_by', 'day')
            )
        );
    }

    public function auditEventDistribution(Request $request): JsonResponse
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;

        return response()->json(
            $this->service->getAuditEventDistribution($from, $to)
        );
    }

    public function inventoryTurnover(Request $request): JsonResponse
    {
        $from = $request->from ? Carbon::parse($request->from) : null;
        $to = $request->to ? Carbon::parse($request->to) : null;

        return response()->json(
            $this->service->getInventoryTurnover(
                $from,
                $to,
                $request->integer('branch_id') ?: null
            )
        );
    }
}
