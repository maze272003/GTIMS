<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController\DashboardController;
use App\Http\Controllers\AdminController\ProductMovementController;
use App\Http\Controllers\AdminController\InventoryController;
use App\Http\Controllers\AdminController\PatientRecordsController;
use App\Http\Controllers\AdminController\HistorylogController;
// Magdagdag dito ng Superadmin controllers mo...
// use App\Http\Controllers\SuperadminController\UserManagementController;

Route::get('/', function () {
    return view('welcome');
});

// Lahat ng routes sa loob nito ay kailangan naka-login (auth, verified)
Route::middleware(['auth', 'verified'])->group(function () {

    // General dashboard for all logged-in users
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Profile routes (para sa lahat ng logged-in)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // =================== SUPERADMIN ROUTES ===================
    // 'can:be-superadmin' -> Gagamitin ang Gate na ginawa natin
    // 'prefix' -> Lahat ng URL sa loob nito ay magsisimula sa /superadmin
    // 'name' -> Lahat ng route name ay magsisimula sa superadmin.
    
    Route::middleware('can:be-superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        
        // Halimbawa: /superadmin/users -> superadmin.users.index
        // Route::get('/users', [UserManagementController::class, 'index'])->name('users.index'); 
        
        // Dito mo ilagay ang iba pang superadmin routes
        
    });

    // =================== ADMIN ROUTES ===================
    // 'can:be-admin' -> Pwede pumasok dito si 'admin' AT 'superadmin'
    // 'prefix' -> /admin
    // 'name' -> admin.
    
    Route::middleware('can:be-admin')->prefix('admin')->name('admin.')->group(function () {
        
        // URL: /admin/dashboard -> Name: admin.dashboard
        Route::get('/dashboard', [DashboardController::class, 'showdashboard'])->name('dashboard');
        
        // URL: /admin/productmovement -> Name: admin.productmovement
        Route::get('/productmovement', [ProductMovementController::class, 'showproductmovement'])->name('productmovement');
        
        // URL: /admin/inventory -> Name: admin.inventory
        Route::get('/inventory', [InventoryController::class, 'showinventory'])->name('inventory');
        
        // URL: /admin/patientrecords -> Name: admin.patientrecords
        Route::get('/patientrecords', [PatientRecordsController::class, 'showpatientrecords'])->name('patientrecords');
        
        // URL: /admin/historylog -> Name: admin.historylog
        Route::get('/historylog', [HistorylogController::class, 'showhistorylog'])->name('historylog');
    });

});

require __DIR__.'/auth.php';