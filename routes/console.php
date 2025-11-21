<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('prod:reset-db', function () {
    // 1. Add a confirmation step (Safety First!)
    if ($this->confirm('DANGER: This will DELETE ALL DATA in production. Are you sure?')) {
        
        $this->comment('Wiping database and re-running migrations...');

        // 2. Call the command with the --force flag
        // The '--force' is REQUIRED for production environments
        $this->call('migrate:fresh', [
            '--force' => true, 
            '--seed' => true // Optional: if you want to run seeders too
        ]);

        $this->info('Database has been reset!');
    } else {
        $this->comment('Operation cancelled.');
    }
})->purpose('Dangerous command to wipe production DB');
