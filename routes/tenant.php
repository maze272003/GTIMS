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
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TenantInvitationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\TenantWebhookController;
use App\Http\Controllers\TenantStorageController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\Api\V1\TenantTokenController;
use App\Http\Controllers\Moderator\ModeratorDashboardController;
use App\Http\Controllers\Moderator\TenantSwitchController;

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
    ->middleware([
        'auth',
        'verified',
        'tenant.resolve',
        'tenant.membership',
        'tenant.bind',
        'tenant.modelscope',
        'tenant.foreign_keys',
    ])
    ->name('tenant.')
    ->group(function () {

        // Tenant Dashboard
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])
            ->middleware('permission:dashboard.view')
            ->name('dashboard');

        // Orders
        Route::prefix('orders')->middleware('permission:orders.view')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/create', [OrderController::class, 'create'])->middleware('permission:orders.create')->name('create');
            Route::post('/store', [OrderController::class, 'store'])->middleware('permission:orders.create')->name('store');
            Route::post('/{id}/update', [OrderController::class, 'updateStatus'])
                ->middleware('permission:orders.approve_admin,orders.approve_finance')
                ->name('update');
            Route::get('/{id}/print', [OrderController::class, 'print'])->name('print');
        });

        // Inventory
        Route::get('/inventory', [InventoryController::class, 'showinventory'])
            ->middleware('permission:inventory.view')
            ->name('inventory');
        Route::post('/inventory/export', [InventoryExportController::class, 'export'])
            ->middleware('permission:reports.export')
            ->middleware('throttle:tenant-export')
            ->name('inventory.export');

        // Patient Records
        Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])
            ->middleware('permission:patients.view')
            ->name('patientrecords');
        Route::post('/patientrecords', [PatientRecordsController::class, 'adddispensation'])
            ->middleware('permission:patients.manage')
            ->name('patientrecords.adddispensation');
        Route::put('/patientrecords', [PatientRecordsController::class, 'updatePatientRecord'])
            ->middleware('permission:patients.manage')
            ->name('patientrecords.update');

        // Holds
        Route::prefix('holds')->middleware('permission:holds.view')->name('holds.')->group(function () {
            Route::get('/', [HoldController::class, 'index'])->name('index');
            Route::get('/create', [HoldController::class, 'create'])->middleware('permission:holds.create')->name('create');
            Route::post('/', [HoldController::class, 'store'])->middleware('permission:holds.create')->name('store');
            Route::get('/{hold}', [HoldController::class, 'show'])->name('show');
        });

        // Incoming Requests
        Route::prefix('requests')->middleware('permission:requests.view')->name('requests.')->group(function () {
            Route::get('/', [IncomingRequestController::class, 'index'])->name('index');
            Route::get('/create', [IncomingRequestController::class, 'create'])->middleware('permission:requests.create')->name('create');
            Route::post('/', [IncomingRequestController::class, 'store'])->middleware('permission:requests.create')->name('store');
            Route::get('/{incomingRequest}', [IncomingRequestController::class, 'show'])->name('show');
        });

        // Suppliers
        Route::prefix('suppliers')->middleware('permission:suppliers.view')->name('suppliers.')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->name('index');
            Route::get('/create', [SupplierController::class, 'create'])->middleware('permission:suppliers.manage')->name('create');
            Route::post('/', [SupplierController::class, 'store'])->middleware('permission:suppliers.manage')->name('store');
            Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('permission:suppliers.manage')->name('edit');
            Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.manage')->name('update');
        });

        // Analytics
        Route::prefix('analytics')->middleware(['permission:reports.view', 'throttle:tenant-api'])->name('analytics.')->group(function () {
            Route::get('/sla-metrics', [AnalyticsApiController::class, 'slaMetrics'])->name('sla');
            Route::get('/reorder-suggestions', [AnalyticsApiController::class, 'reorderSuggestions'])->name('reorder');
            Route::get('/low-stock-alerts', [AnalyticsApiController::class, 'lowStockAlerts'])->name('low-stock');
            Route::get('/stock-kpis', [AnalyticsApiController::class, 'stockKPIs'])->name('kpis');
        });

        // Tenant Webhooks
        Route::prefix('webhooks')->middleware('permission:settings.roles')->name('webhooks.')->group(function () {
            Route::get('/', [TenantWebhookController::class, 'index'])->name('index');
            Route::post('/', [TenantWebhookController::class, 'store'])->name('store');
            Route::delete('/{webhook}', [TenantWebhookController::class, 'destroy'])->name('destroy');
            Route::post('/{webhook}/test', [TenantWebhookController::class, 'test'])->name('test');
        });

        // Notifications
        Route::prefix('notifications')->middleware('permission:notifications.manage')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        });

        // Audit
        Route::prefix('audit')->middleware('permission:audit.view')->name('audit.')->group(function () {
            Route::get('/', [AuditEventController::class, 'index'])->name('index');
            Route::get('/{auditEvent}', [AuditEventController::class, 'show'])->name('show');
        });

        Route::get('/storage/download', [TenantStorageController::class, 'download'])
            ->middleware('signed')
            ->name('storage.download');

        Route::get('/settings', [TenantSettingsController::class, 'index'])
            ->middleware('permission:settings.roles')
            ->name('settings.index');
        Route::put('/settings', [TenantSettingsController::class, 'update'])
            ->middleware('permission:settings.roles')
            ->name('settings.update');
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
    ->middleware(['auth:web,moderator', 'verified', 'moderator.only'])
    ->name('moderator.')
    ->group(function () {

        Route::get('/dashboard', [ModeratorDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/provinces', [ModeratorDashboardController::class, 'provinces'])->name('provinces.index');
        Route::post('/provinces', [ModeratorDashboardController::class, 'storeProvince'])->name('provinces.store');
        Route::get('/barangays', [ModeratorDashboardController::class, 'barangays'])->name('barangays.index');
        Route::post('/barangays', [ModeratorDashboardController::class, 'storeBarangay'])->name('barangays.store');
        Route::get('/memberships', [ModeratorDashboardController::class, 'memberships'])->name('memberships.index');
        Route::post('/memberships', [ModeratorDashboardController::class, 'storeMembership'])->name('memberships.store');
        Route::get('/onboarding', [ModeratorDashboardController::class, 'onboarding'])->name('onboarding.index');
        Route::post('/onboarding/{onboarding}/advance', [ModeratorDashboardController::class, 'advanceOnboarding'])->name('onboarding.advance');
        Route::get('/incidents', [ModeratorDashboardController::class, 'incidents'])->name('incidents.index');
        Route::put('/incidents/{incident}', [ModeratorDashboardController::class, 'updateIncident'])->name('incidents.update');

        Route::post('/switch-tenant', [TenantSwitchController::class, 'switch'])->name('switch');
        Route::post('/switch-platform', [TenantSwitchController::class, 'switchPlatform'])->name('switch.platform');
        Route::post('/api-tokens', [TenantTokenController::class, 'issue'])->name('api-tokens.issue');
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
    ->middleware(['tenant.resolve', 'tenant.bind', 'guest'])
    ->name('tenant.')
    ->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:tenant-login')
            ->name('login.submit');

        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
            ->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');
    });

Route::prefix('{provinceSlug}/{barangaySlug}')
    ->middleware(['tenant.resolve', 'tenant.bind'])
    ->name('tenant.')
    ->group(function () {
        Route::get('/invite/accept/{token}', [TenantInvitationController::class, 'accept'])
            ->name('invite.accept');
    });

Route::prefix('{provinceSlug}/{barangaySlug}')
    ->middleware(['auth', 'tenant.resolve', 'tenant.membership', 'tenant.bind', 'tenant.modelscope'])
    ->name('tenant.')
    ->group(function () {
        Route::get('/verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

Route::prefix('moderator')
    ->middleware('guest:web,moderator')
    ->name('moderator.')
    ->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:moderator-login')
            ->name('login.submit');

        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
            ->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');
    });

Route::prefix('moderator')
    ->middleware('auth:web,moderator')
    ->name('moderator.')
    ->group(function () {
        Route::get('/verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });
