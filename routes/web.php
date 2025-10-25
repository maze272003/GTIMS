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

Route::get('/', function () {
    return view('auth.login');
});

// Lahat ng routes sa loob nito ay kailangan naka-login (auth, verified)
Route::middleware(['auth', 'verified'])->group(function () {

    // =================== 1. ANG LOGIN REDIRECTOR ===================
    // Ito ang sasalubong sa LAHAT ng user pagka-login.
    // Ipadadala niya ang LAHAT (superadmin, admin, encoder) sa 'admin.dashboard'.
    
    Route::get('/dashboard', function () {
        
        // I-check kung may permission siyang pumasok sa admin panel
        // gamit ang Gate na ginawa natin sa AppServiceProvider
        if (Auth::user()->can('can-access-admin-panel')) {
             // Papuntang /admin/dashboard
            return redirect()->route('admin.dashboard');
        }

        // Kung wala (halimbawa, ibang role na hindi kasama), logout
        Auth::logout();
        return redirect('/login')->with('error', 'You do not have permission.');

    })->name('dashboard'); // <-- Ito ang default "home" ng Laravel

    
    // =================== 2. PROFILE ROUTES ===================
    // (Para sa lahat ng naka-login)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // =================== 3. ANG IISANG (SHARED) ADMIN PANEL ===================
    // Pinagsama-sama na natin ang lahat dito.
    // Dito papasok si SUPERADMIN, ADMIN, at ENCODER.
    
    Route::middleware('can:can-access-admin-panel') // <-- Gamit ang bagong Gate
         ->prefix('admin') // <-- Lahat ay /admin/...
         ->name('admin.') // <-- Lahat ay may pangalang admin....
         ->group(function () {
        
        // == SHARED ROUTES PARA SA SUPERADMIN, ADMIN, AT ENCODER ==
        
        // URL: /admin/dashboard -> Name: admin.dashboard
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');
        
        // URL: /admin/productmovement -> Name: admin.productmovement
        Route::get('/productmovement', [ProductMovementController::class, 'showproductmovement'])->name('productmovement');
        
        // URL: /admin/inventory -> Name: admin.inventory
        Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
        Route::post('/inventory', [InventoryController::class, 'addProduct'])->name('inventory.addproduct');
        Route::put('/inventory/update', [InventoryController::class, 'updateProduct'])->name('inventory.updateproduct');
        Route::post('/inventory/addstock', [InventoryController::class, 'addStock'])->name('inventory.addstock');
        Route::put('/inventory/editstock', [InventoryController::class, 'editStock'])->name('inventory.editstock');
        
        // URL: /admin/patientrecords -> Name: admin.patientrecords
        Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
        
        // URL: /admin/historylog -> Name: admin.historylog
        Route::get('/historylog', [HistorylogController::class, 'showhistorylog'])->name('historylog');

        
        // == ROUTE NA PARA SA SUPERADMIN LANG ==
        // Nasa loob pa rin ng /admin/ path, pero may extra check
        
        // URL: /admin/manageaccount -> Name: admin.manageaccount
        Route::get('/manageaccount' , [ManageaccountController::class, 'showManageaccount'])
             ->middleware('can:be-superadmin') // <-- Dito chine-check kung superadmin
             ->name('manageaccount');
    });

    // WALA NA DITO 'YUNG MGA HIWALAY NA ROUTE GROUP PARA SA
    // 'superadmin', 'admin', at 'encoder' DAHIL PINAG-ISA NA SA TAAS.

});

require __DIR__.'/auth.php';