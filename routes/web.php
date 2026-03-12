<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\AdminController\OrderController;
use App\Http\Controllers\AdminController\DashboardController;
use App\Http\Controllers\AdminController\InventoryController;
use App\Http\Controllers\AdminController\HistorylogController;
use App\Http\Controllers\AdminController\ManageaccountController;
use App\Http\Controllers\AdminController\PatientRecordsController;
use App\Http\Controllers\AdminController\InventoryExportController;
use App\Http\Controllers\AdminController\ProductMovementController;
use App\Http\Controllers\Admin\HoldController;
use App\Http\Controllers\Admin\IncomingRequestController;
use App\Http\Controllers\Admin\LowStockSettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\AuditEventController;
use App\Http\Controllers\Admin\AnalyticsApiController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\BranchManagementController;
use App\Http\Controllers\Admin\SystemAnalyticsController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Services\AuthSessionService;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('auth.login');
});

Route::post('/send-otp', [OtpLoginController::class, 'sendOtp'])->middleware('throttle:5,1')->name('otp.send');
Route::post('/verify-otp', [OtpLoginController::class, 'verifyOtp'])->middleware('throttle:5,1')->name('otp.verify');
Route::get('/verify-account/{id}', [ManageaccountController::class, 'verifyAccount'])
    ->name('account.verify')
    ->middleware('signed');
// Lahat ng routes sa loob nito ay kailangan naka-login (auth, verified)
Route::middleware(['auth', 'verified'])->group(function () {

    // =================== 1. ANG LOGIN REDIRECTOR ===================
    // Ito ang sasalubong sa LAHAT ng user pagka-login.
    Route::get('/dashboard', function (AuthSessionService $authSessionService) {
        $user = Auth::user();

        if (!$user || !$user->level) {
            Auth::logout();
            return redirect('/login')->with('error', 'You do not have permission.');
        }

        $redirectUrl = $authSessionService->getRedirectUrl($user);
        if ($redirectUrl) {
            return redirect()->to($redirectUrl);
        }

        Auth::logout();
        return redirect('/login')->with('error', 'You do not have permission.');

    })->name('dashboard'); // <-- Ito ang default "home" ng Laravel

    // =================== 2. PROFILE ROUTES ===================
    // (Para sa lahat ng naka-login)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // =================== 3. ANG IISANG (SHARED) ADMIN PANEL ===================
    //
    // ---- ITO NA ANG MALINIS NA VERSION GAMIT ANG MGA GINAWA NATING MIDDLEWARE ----
    //
    Route::prefix('admin')
          ->name('admin.')
          ->middleware(['level.all', 'admin.permission']) // L1, L2, L3, L4 CAN ENTER THIS BLOCK
          ->group(function () {

        // == A. BASE ACCESS ROUTES (Para sa lahat ng nakapasa sa level.all) ==
        // L1, L2, L3, L4: Dashboard.
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');

             Route::prefix('orders')->name('orders.')->group(function () {
                Route::get('/', [OrderController::class, 'index'])->name('index');
                Route::get('/create', [OrderController::class, 'create'])->name('create');
                Route::post('/store', [OrderController::class, 'store'])->name('store');
                Route::get('/source-inventory', [OrderController::class, 'sourceInventoryOptions'])->name('source-inventory');
                Route::post('/{id}/update', [OrderController::class, 'updateStatus'])->name('update');
                Route::get('/{id}/print', [OrderController::class, 'print'])->name('print');
            });

        // Patient Records routes
        Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
        Route::post('/patientrecords', [PatientRecordsController::class, 'adddispensation'])->name('patientrecords.adddispensation');
        Route::put('/patientrecords', [PatientRecordsController::class, 'updatePatientRecord'])->name('patientrecords.update');
        Route::get('/patientrecords/export-pdf', [PatientRecordsController::class, 'exportPdf'])->name('patientrecords.exportPdf');
        Route::get('/patientrecords/export-excel', [PatientRecordsController::class, 'exportExcel'])
            ->name('patientrecords.exportExcel');

        Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
        Route::post('/inventory/export', [InventoryExportController::class, 'export'])->name('inventory.export');

        // == B. ADMIN/SUPERADMIN ROUTES (Level 1, 2 ONLY) ==
        // SECURITY CHECK: Lahat ng routes dito ay mahigpit na protektado ng level.admin (L1, L2)
        // Ito ang pumipigil sa Doctor (L4) na i-access ang mga paths na ito, kahit manual niyang i-edit ang URL.
        Route::middleware('level.all')
             ->group(function () {

            // L1, L2: Product Movements (Protected)
            Route::get('/product-movements', [ProductMovementController::class, 'showMovements'])->name('movements');
            Route::post('/get-ai-analysis', [DashboardController::class, 'getAiAnalysis'])->name('ai.analysis');

            // --- Inventory Routes (Protected) ---
            // Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
            Route::post('/inventory', [InventoryController::class, 'addProduct'])->name('inventory.addproduct');
            Route::put('/inventory/update', [InventoryController::class, 'updateProduct'])->name('inventory.updateproduct');
            Route::post('/inventory/addstock', [InventoryController::class, 'addStock'])->name('inventory.addstock');
            Route::put('/inventory/editstock', [InventoryController::class, 'editStock'])->name('inventory.editstock');
            Route::put('/inventory/archive', [InventoryController::class, 'archiveProduct'])->name('inventory.archiveproduct');
            Route::put('/inventory/unarchive', [InventoryController::class, 'unarchiveProduct'])->name('inventory.unarchiveproduct');
            Route::get('/inventory/archived-stocks', [InventoryController::class, 'fetchArchivedStocks'])
                 ->name('inventory.fetchArchivedStocks');

            Route::post('/inventory/transfer', [InventoryController::class, 'transferStock'])->name('inventory.transferstock');



            // L1, L2: History Logs (Protected)
            Route::get('/historylog', [HistorylogController::class, 'showhistorylog'])->name('historylog');

            // == Holds/Pullout Routes ==
            Route::prefix('holds')->name('holds.')->group(function () {
                Route::get('/', [HoldController::class, 'index'])->name('index');
                Route::get('/create', [HoldController::class, 'create'])->name('create');
                Route::post('/', [HoldController::class, 'store'])->name('store');
                Route::get('/{hold}', [HoldController::class, 'show'])->name('show');
                Route::post('/{hold}/approve', [HoldController::class, 'approve'])->name('approve');
                Route::put('/{hold}/release', [HoldController::class, 'release'])->name('release');
                Route::put('/{hold}/cancel', [HoldController::class, 'cancel'])->name('cancel');
                Route::post('/pull-out', [HoldController::class, 'pullOut'])->name('pullout');
            });

            // == Incoming Requests Workflow Routes ==
            Route::prefix('requests')->name('requests.')->group(function () {
                Route::get('/', [IncomingRequestController::class, 'index'])->name('index');
                Route::get('/create', [IncomingRequestController::class, 'create'])->name('create');
                Route::post('/', [IncomingRequestController::class, 'store'])->name('store');
                Route::get('/{incomingRequest}', [IncomingRequestController::class, 'show'])->name('show');
                Route::post('/{incomingRequest}/transition', [IncomingRequestController::class, 'transition'])->name('transition');
                Route::post('/{incomingRequest}/fulfill', [IncomingRequestController::class, 'fulfill'])->name('fulfill');
                Route::post('/{incomingRequest}/comment', [IncomingRequestController::class, 'addComment'])->name('comment');
                Route::post('/{incomingRequest}/attachment', [IncomingRequestController::class, 'addAttachment'])->name('attachment');
            });

            // == Low Stock Settings Routes ==


            // == Supplier Routes ==
            Route::prefix('suppliers')->name('suppliers.')->group(function () {
                Route::get('/', [SupplierController::class, 'index'])->name('index');
                Route::get('/create', [SupplierController::class, 'create'])->name('create');
                Route::get('/export-excel', [SupplierController::class, 'exportExcel'])->name('exportExcel');
                Route::post('/', [SupplierController::class, 'store'])->name('store');
                Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
                Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
                Route::post('/{supplier}/link-inventory', [SupplierController::class, 'linkInventory'])->name('link-inventory');
                Route::delete('/{supplier}/unlink-inventory/{inventory}', [SupplierController::class, 'unlinkInventory'])->name('unlink-inventory');
            });

            // == Audit Events Routes ==
            Route::prefix('audit')->name('audit.')->group(function () {
                Route::get('/', [AuditEventController::class, 'index'])->name('index');
                Route::get('/{auditEvent}', [AuditEventController::class, 'show'])->name('show');
            });

            // == Analytics API Routes ==
            Route::prefix('analytics')->name('analytics.')->group(function () {
                Route::get('/sla-metrics', [AnalyticsApiController::class, 'slaMetrics'])->name('sla');
                Route::get('/reorder-suggestions', [AnalyticsApiController::class, 'reorderSuggestions'])->name('reorder');
                Route::get('/low-stock-alerts', [AnalyticsApiController::class, 'lowStockAlerts'])->name('low-stock');
                Route::get('/stock-kpis', [AnalyticsApiController::class, 'stockKPIs'])->name('kpis');

                // System Analytics & Observability
                Route::get('/overview', [SystemAnalyticsController::class, 'overview'])->name('overview');
                Route::get('/inventory-movement-trends', [SystemAnalyticsController::class, 'inventoryMovementTrends'])->name('inventory-movement-trends');
                Route::get('/stock-level-distribution', [SystemAnalyticsController::class, 'stockLevelDistribution'])->name('stock-level-distribution');
                Route::get('/expiry-tracking', [SystemAnalyticsController::class, 'expiryTracking'])->name('expiry-tracking');
                Route::get('/request-status-distribution', [SystemAnalyticsController::class, 'requestStatusDistribution'])->name('request-status-distribution');
                Route::get('/request-volume-trends', [SystemAnalyticsController::class, 'requestVolumeTrends'])->name('request-volume-trends');
                Route::get('/hold-analytics', [SystemAnalyticsController::class, 'holdAnalytics'])->name('hold-analytics');
                Route::get('/user-activity-trends', [SystemAnalyticsController::class, 'userActivityTrends'])->name('user-activity-trends');
                Route::get('/audit-event-distribution', [SystemAnalyticsController::class, 'auditEventDistribution'])->name('audit-event-distribution');
                Route::get('/inventory-turnover', [SystemAnalyticsController::class, 'inventoryTurnover'])->name('inventory-turnover');
            });

            // == Notification Routes ==
            Route::prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
                Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('read-all');
                Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
                Route::post('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
            });

            //  Route::prefix('low-stock-settings')->name('lowstock.')->group(function () {
            //     Route::get('/', [LowStockSettingController::class, 'index'])->name('index');
            //     Route::post('/global', [LowStockSettingController::class, 'updateGlobal'])->name('global');
            //     Route::post('/override', [LowStockSettingController::class, 'storeOverride'])->name('override');
            //     Route::delete('/override/{setting}', [LowStockSettingController::class, 'destroyOverride'])->name('override.destroy');
            // });
            Route::prefix('low-stock-settings')->name('lowstock.')->group(function () {
        Route::get('/', [LowStockSettingController::class, 'index'])->name('index');
        Route::get('/filter-options', [LowStockSettingController::class, 'filterOptions'])->name('filter-options');

        // global threshold
        Route::post('/global', [LowStockSettingController::class, 'updateGlobal'])->name('global');

        // per-branch default threshold (this fixes: Route [lowstock.branchDefault] not defined)
        Route::post('/branch-default', [LowStockSettingController::class, 'storeBranchDefault'])
            ->name('branchDefault');

        // per product override (optionally per branch)
        Route::post('/override', [LowStockSettingController::class, 'storeOverride'])->name('override');

        // delete override
        Route::delete('/override/{setting}', [LowStockSettingController::class, 'destroyOverride'])
            ->name('override.destroy');
    });

        });

        // == C. SUPERADMIN ONLY ROUTES (Level 1) ==
        // SECURITY CHECK: Lahat ng routes dito ay mahigpit na protektado ng level.superadmin (L1)
        Route::middleware('level.all')
             ->group(function () {

            // post for create account
           Route::post('/manageaccount', [ManageaccountController::class, 'store'])
                ->name('manageaccount.store');

// IDAGDAG ITO para gumana ang Edit:
            Route::put('/manageaccount/{id}', [ManageaccountController::class, 'update'])
                ->name('manageaccount.update');
            // L1: Manage Account (Protected)
            Route::get('/manageaccount' , [ManageaccountController::class, 'showManageaccount'])
                  ->name('manageaccount');

            // L1: Role/Permission Management
            Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index')->middleware('permission:settings.roles');
            Route::post('/roles', [RolePermissionController::class, 'update'])->name('roles.update')->middleware('permission:settings.roles');

            // L1: Branch Management
            Route::prefix('branches')
                ->name('branches.')
                ->middleware(['level.superadmin', 'permission:branches.manage'])
                ->group(function () {
                    Route::get('/', [BranchManagementController::class, 'index'])->name('index');
                    Route::post('/', [BranchManagementController::class, 'store'])->name('store');
                    Route::post('/{branch}/set-main', [BranchManagementController::class, 'setMain'])->name('set-main');
                    Route::post('/{branch}/archive', [BranchManagementController::class, 'archive'])->name('archive');
                    Route::post('/runs/{run}/rollback', [BranchManagementController::class, 'rollback'])->name('rollback');
                });
        });

        // == D. AUTOMATION BUILDER ROUTES ==
        Route::prefix('workflows')->name('workflows.')->middleware('permission:workflows.view')->group(function () {
            Route::get('/', [WorkflowController::class, 'index'])->name('index');
            Route::post('/', [WorkflowController::class, 'store'])->name('store')->middleware('permission:workflows.create');
            Route::get('/catalog', [WorkflowController::class, 'catalog'])->name('catalog');
            Route::get('/templates', [WorkflowController::class, 'templates'])->name('templates');
            Route::get('/{workflow}/editor', [WorkflowController::class, 'editor'])->name('editor');
            Route::get('/{workflow}/graph-state', [WorkflowController::class, 'graphState'])->name('graph-state');
            Route::post('/{workflow}/save-graph', [WorkflowController::class, 'saveGraph'])->name('save-graph')->middleware('permission:workflows.edit');
            Route::post('/{workflow}/validate', [WorkflowController::class, 'validate'])->name('validate');
            Route::post('/{workflow}/publish', [WorkflowController::class, 'publish'])->name('publish')->middleware('permission:workflows.publish');
            Route::post('/{workflow}/disable', [WorkflowController::class, 'disable'])->name('disable')->middleware('permission:workflows.edit');
            Route::post('/{workflow}/run', [WorkflowController::class, 'run'])->name('run')->middleware('permission:workflows.run');
            Route::get('/{workflow}/runs', [WorkflowController::class, 'runs'])->name('runs');
            Route::get('/{workflow}/runs/{run}', [WorkflowController::class, 'showRun'])->name('runs.show');
            Route::delete('/{workflow}', [WorkflowController::class, 'destroy'])->name('destroy')->middleware('permission:workflows.delete');
            Route::get('/{workflow}/permissions', [WorkflowController::class, 'permissions'])->name('permissions');
            Route::post('/{workflow}/permissions', [WorkflowController::class, 'addPermission'])->name('permissions.add')->middleware('permission:workflows.edit');
            Route::delete('/{workflow}/permissions/{permission}', [WorkflowController::class, 'removePermission'])->name('permissions.remove')->middleware('permission:workflows.edit');
            // Version history & rollback
            Route::get('/{workflow}/versions', [WorkflowController::class, 'versionHistory'])->name('versions');
            Route::post('/{workflow}/versions/{version}/rollback', [WorkflowController::class, 'rollbackVersion'])->name('versions.rollback')->middleware('permission:workflows.publish');
            // Dead-letter & rerun
            Route::get('/{workflow}/dead-letter', [WorkflowController::class, 'deadLetterRuns'])->name('dead-letter');
            Route::post('/{workflow}/runs/{run}/rerun', [WorkflowController::class, 'rerunFailedRun'])->name('runs.rerun')->middleware('permission:workflows.run');
        });

    }); // <-- End ng buong /admin group


}); // <-- End ng buong auth middleware group



require __DIR__.'/auth.php';
// SECURITY: db.php contains a dangerous database reset route.
// Only include it in local/development environments.
if (app()->environment('local')) {
    require __DIR__.'/db.php';
}
