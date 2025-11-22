<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController\DashboardController;
use App\Http\Controllers\AdminController\ProductMovementController;
use App\Http\Controllers\AdminController\InventoryController;
use App\Http\Controllers\AdminController\PatientRecordsController;
use App\Http\Controllers\AdminController\HistorylogController;
use App\Http\Controllers\AdminController\InventoryExportController;
use App\Http\Controllers\AdminController\ManageaccountController;
use Illuminate\Support\Facades\Auth; // <-- Siguraduhin na nandito ito
use App\Http\Controllers\Auth\OtpLoginController;

Route::get('/', function () {
    return view('auth.login');
});

Route::post('/send-otp', [OtpLoginController::class, 'sendOtp'])->name('otp.send');
Route::post('/verify-otp', [OtpLoginController::class, 'verifyOtp'])->name('otp.verify');
Route::get('/verify-account/{id}', [ManageaccountController::class, 'verifyAccount'])
    ->name('account.verify')
    ->middleware('signed');
// Lahat ng routes sa loob nito ay kailangan naka-login (auth, verified)
Route::middleware(['auth', 'verified'])->group(function () {

    // =================== 1. ANG LOGIN REDIRECTOR ===================
    // Ito ang sasalubong sa LAHAT ng user pagka-login.
    Route::get('/dashboard', function () {
        
        // ---- BINAGO NATIN TO ----
        // I-check kung ang level ay 1, 2, o 3 (Superadmin, Admin, o Encoder)
        // (Ito ay kapareho ng logic ng 'level.all' middleware)
        if (Auth::user() && in_array(Auth::user()->user_level_id, [1, 2, 3])) {
             // Papuntang /admin/dashboard
            return redirect()->route('admin.dashboard');
        }

        // Kung wala (level 4, 5, atbp), logout
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
          ->middleware('level.all') // L1, L2, L3, L4 CAN ENTER THIS BLOCK
          ->group(function () {
        
        // == A. BASE ACCESS ROUTES (Para sa lahat ng nakapasa sa level.all) ==
        // L1, L2, L3, L4: Dashboard.
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');
        
        // L1, L2, L4: Patient Records READ access. 
        // Ang access check para dito ay nasa loob ng PatientRecordsController (L1, L2, L4 allowed, L3 blocked).
        Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
        // --- Iba pang Admin Routes ---
            Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
            Route::post('/patientrecords', [PatientRecordsController::class, 'adddispensation'])->name('patientrecords.adddispensation');
            Route::put('/patientrecords', [PatientRecordsController::class, 'updatePatientRecord'])->name('patientrecords.update');

        Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
        Route::post('/inventory/export', [InventoryExportController::class, 'export'])->name('inventory.export');
        
        // == B. ADMIN/SUPERADMIN ROUTES (Level 1, 2 ONLY) ==
        // SECURITY CHECK: Lahat ng routes dito ay mahigpit na protektado ng level.admin (L1, L2)
        // Ito ang pumipigil sa Doctor (L4) na i-access ang mga paths na ito, kahit manual niyang i-edit ang URL.
        Route::middleware('level.admin') 
             ->group(function () {
            
            // L1, L2: Patient Records WRITE access (Add Dispensation)
            Route::post('/patientrecords', [PatientRecordsController::class, 'adddispensation'])->name('patientrecords.adddispensation');
            
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
                 ->name('admin.inventory.fetchArchivedStocks');

            Route::post('/inventory/transfer', [InventoryController::class, 'transferStock'])->name('inventory.transferstock');

                 

            // L1, L2: History Logs (Protected)
            Route::get('/historylog', [HistorylogController::class, 'showhistorylog'])->name('historylog');
        });

        // == C. SUPERADMIN ONLY ROUTES (Level 1) ==
        // SECURITY CHECK: Lahat ng routes dito ay mahigpit na protektado ng level.superadmin (L1)
        Route::middleware('level.superadmin') 
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
        });
        
    }); // <-- End ng buong /admin group

}); // <-- End ng buong auth middleware group

require __DIR__.'/auth.php';
require __DIR__.'/db.php';