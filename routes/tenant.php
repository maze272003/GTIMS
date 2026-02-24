<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController\DashboardController;
use App\Http\Controllers\AdminController\OrderController;
use App\Http\Controllers\AdminController\InventoryController;
use App\Http\Controllers\AdminController\PatientRecordsController;
use App\Http\Controllers\AdminController\InventoryExportController;
use App\Http\Controllers\AdminController\ProductMovementController;
use App\Http\Controllers\AdminController\HistorylogController;
use App\Http\Controllers\AdminController\ManageaccountController;
use App\Http\Controllers\Admin\HoldController;
use App\Http\Controllers\Admin\IncomingRequestController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\AuditEventController;
use App\Http\Controllers\Admin\AnalyticsApiController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\LowStockSettingController;
use App\Http\Controllers\Admin\RolePermissionController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are scoped to a specific province/barangay tenant via
| URL path slugs: /{provinceSlug}/{barangaySlug}/...
|
| Middleware stack:
| 1. auth + verified - user must be logged in
| 2. tenant.resolve  - resolves tenant from URL slugs
| 3. tenant.membership - verifies user has access to this tenant
| 4. tenant.bind - binds TenantContext to app container
|
*/

Route::prefix('{provinceSlug}/{barangaySlug}')
    ->middleware(['auth', 'verified', 'tenant.resolve', 'tenant.membership', 'tenant.bind'])
    ->name('tenant.')
    ->group(function () {

        // Tenant Dashboard
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/create', [OrderController::class, 'create'])->name('create');
            Route::post('/store', [OrderController::class, 'store'])->name('store');
            Route::post('/{id}/update', [OrderController::class, 'updateStatus'])->name('update');
            Route::get('/{id}/print', [OrderController::class, 'print'])->name('print');
        });

        // Inventory
        Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
        Route::post('/inventory/export', [InventoryExportController::class, 'export'])->name('inventory.export');

        // Patient Records
        Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
        Route::post('/patientrecords', [PatientRecordsController::class, 'adddispensation'])->name('patientrecords.adddispensation');
        Route::put('/patientrecords', [PatientRecordsController::class, 'updatePatientRecord'])->name('patientrecords.update');

        // Holds
        Route::prefix('holds')->name('holds.')->group(function () {
            Route::get('/', [HoldController::class, 'index'])->name('index');
            Route::get('/create', [HoldController::class, 'create'])->name('create');
            Route::post('/', [HoldController::class, 'store'])->name('store');
            Route::get('/{hold}', [HoldController::class, 'show'])->name('show');
        });

        // Incoming Requests
        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [IncomingRequestController::class, 'index'])->name('index');
            Route::get('/create', [IncomingRequestController::class, 'create'])->name('create');
            Route::post('/', [IncomingRequestController::class, 'store'])->name('store');
            Route::get('/{incomingRequest}', [IncomingRequestController::class, 'show'])->name('show');
        });

        // Suppliers
        Route::prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->name('index');
            Route::get('/create', [SupplierController::class, 'create'])->name('create');
            Route::post('/', [SupplierController::class, 'store'])->name('store');
            Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
            Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
        });

        // Analytics
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/sla-metrics', [AnalyticsApiController::class, 'slaMetrics'])->name('sla');
            Route::get('/reorder-suggestions', [AnalyticsApiController::class, 'reorderSuggestions'])->name('reorder');
            Route::get('/low-stock-alerts', [AnalyticsApiController::class, 'lowStockAlerts'])->name('low-stock');
            Route::get('/stock-kpis', [AnalyticsApiController::class, 'stockKPIs'])->name('kpis');
        });

        // Notifications
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        });

        // Audit
        Route::prefix('audit')->name('audit.')->group(function () {
            Route::get('/', [AuditEventController::class, 'index'])->name('index');
            Route::get('/{auditEvent}', [AuditEventController::class, 'show'])->name('show');
        });
    });

/*
|--------------------------------------------------------------------------
| Moderator Routes
|--------------------------------------------------------------------------
|
| Platform-level admin routes for the Moderator (Super Admin) role.
| Accessible at /moderator/...
|
*/

Route::prefix('moderator')
    ->middleware(['auth', 'verified'])
    ->name('moderator.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');

        // Province Management (placeholder for future implementation)
        Route::get('/provinces', function () {
            return response()->json(['message' => 'Province management coming soon']);
        })->name('provinces.index');

        // Barangay Management (placeholder)
        Route::get('/barangays', function () {
            return response()->json(['message' => 'Barangay management coming soon']);
        })->name('barangays.index');
    });

/*
|--------------------------------------------------------------------------
| Tenant Login Routes
|--------------------------------------------------------------------------
|
| Login pages scoped to a specific tenant.
|
*/

Route::prefix('{provinceSlug}/{barangaySlug}')
    ->middleware(['tenant.resolve', 'tenant.bind'])
    ->name('tenant.')
    ->group(function () {
        Route::get('/login', function () {
            $tenantContext = app(\App\Tenancy\TenantContext::class);
            return view('auth.login', ['tenantContext' => $tenantContext]);
        })->name('login');
    });

Route::get('/moderator/login', function () {
    return view('auth.login', ['isModerator' => true]);
})->name('moderator.login');
