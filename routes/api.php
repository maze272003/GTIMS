<?php

use App\Http\Controllers\Api\V1\TenantAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/{provinceSlug}/{barangaySlug}')
    ->middleware([
        'tenant.resolve',
        'tenant.bind',
        'tenant.api.auth',
        'tenant.api.match',
        'tenant.modelscope',
        'tenant.foreign_keys',
        'throttle:tenant-api',
    ])
    ->name('api.v1.tenant.')
    ->group(function () {
        Route::get('/analytics/sla', [TenantAnalyticsController::class, 'sla'])
            ->middleware('tenant.api.ability:analytics.read')
            ->name('analytics.sla');

        Route::get('/analytics/reorder', [TenantAnalyticsController::class, 'reorder'])
            ->middleware('tenant.api.ability:analytics.read')
            ->name('analytics.reorder');

        Route::get('/analytics/low-stock', [TenantAnalyticsController::class, 'lowStock'])
            ->middleware('tenant.api.ability:analytics.read')
            ->name('analytics.low-stock');

        Route::get('/analytics/kpis', [TenantAnalyticsController::class, 'kpis'])
            ->middleware('tenant.api.ability:analytics.read')
            ->name('analytics.kpis');
    });

