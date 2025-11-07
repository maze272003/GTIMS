<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController\DashboardController;
use App\Http\Controllers\AdminController\ProductMovementController;
use App\Http\Controllers\AdminController\InventoryController;
use App\Http\Controllers\AdminController\PatientRecordsController;
use App\Http\Controllers\AdminController\HistorylogController;
use App\Http\Controllers\AdminController\ManageaccountController;
use Illuminate\Support\Facades\Auth; // <-- Siguraduhin na nandito ito
use App\Http\Controllers\Auth\OtpLoginController;

Route::get('/', function () {
    return view('auth.login');
});

Route::post('/send-otp', [OtpLoginController::class, 'sendOtp'])->name('otp.send');
Route::post('/verify-otp', [OtpLoginController::class, 'verifyOtp'])->name('otp.verify');
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
          ->middleware('level.all') // <-- CHECK MUNA KUNG LEVEL 1, 2, o 3
          ->group(function () {
        
        // == ROUTE PARA SA LAHAT (Level 1, 2, 3) ==
        // (Automatic pwede na sila dito)
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');
        
        // == ROUTES PARA SA ADMIN at SUPERADMIN (Level 1, 2) ==
        Route::middleware('level.admin') // <-- CHECK KUNG LEVEL 1 o 2
             ->group(function () {
            
Route::get('/product-movements', [ProductMovementController::class, 'showMovements'])->name('movements');    
Route::post('/get-ai-analysis', [DashboardController::class, 'getAiAnalysis'])->name('ai.analysis');        
            // --- Inventory Routes ---
            Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
            Route::post('/inventory', [InventoryController::class, 'addProduct'])->name('inventory.addproduct');
            Route::put('/inventory/update', [InventoryController::class, 'updateProduct'])->name('inventory.updateproduct');
            Route::post('/inventory/addstock', [InventoryController::class, 'addStock'])->name('inventory.addstock');
            Route::put('/inventory/editstock', [InventoryController::class, 'editStock'])->name('inventory.editstock');
            Route::put('/inventory/archive', [InventoryController::class, 'archiveProduct'])->name('inventory.archiveproduct');
            Route::put('/inventory/unarchive', [InventoryController::class, 'unarchiveProduct'])->name('inventory.unarchiveproduct');
            Route::get('/inventory/archived-stocks', [InventoryController::class, 'fetchArchivedStocks'])
             ->name('admin.inventory.fetchArchivedStocks');
            
            // --- Iba pang Admin Routes ---
            Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
            Route::post('/patientrecords', [PatientRecordsController::class, 'adddispensation'])->name('patientrecords.adddispensation');

            Route::get('/historylog', [HistorylogController::class, 'showhistorylog'])->name('historylog');
        });

        // == ROUTE PARA SA SUPERADMIN LANG (Level 1) ==
        Route::middleware('level.superadmin') // <-- CHECK KUNG LEVEL 1 LANG
             ->group(function () {

            Route::get('/manageaccount' , [ManageaccountController::class, 'showManageaccount'])
                  ->name('manageaccount');
        });
    
    }); // <-- End ng buong /admin group

}); // <-- End ng buong auth middleware group

require __DIR__.'/auth.php';