<?php

// === DANGEROUS: RESET DB ROUTE FOR TESTING PURPOSES ONLY or FOR AUTOMATION PORPOSES===
// copy this url to reset the db without using ssh access
// http://127.0.0.1:8000/dangerous-db-reset?key=resetdb or http://gtims.hostcluster.site/dangerous-db-reset?key=resetdb
use Illuminate\Support\Facades\Artisan;
Route::get('/dangerous-db-reset', function () {
    
    if (request()->query('key') !== 'resetdb') {
        abort(403, 'Unauthorized action.');
    }

    set_time_limit(0);
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    
    return "Database reset.";
});
// === DANGEROUS: RESET DB ROUTE FOR TESTING PURPOSES ONLY or FOR AUTOMATION PORPOSES===