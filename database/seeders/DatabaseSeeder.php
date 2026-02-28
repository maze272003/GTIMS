<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Single entry point so `php artisan migrate:fresh --seed` seeds everything.
        $this->call([
            DemoSeeder::class,
        ]);
    }
}
