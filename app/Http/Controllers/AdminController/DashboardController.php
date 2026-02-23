<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\DashboardAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardAdminService $dashboardAdminService
    ) {
    }

    public function showdashboard(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->dashboardAdminService->showdashboard($request);
    }

    public function getAiAnalysis(Request $request): JsonResponse
    {
        return $this->dashboardAdminService->getAiAnalysis($request);
    }
}

