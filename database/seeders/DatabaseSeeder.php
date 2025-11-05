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
        // Tawagin muna ang UserLevelSeeder para may laman
        // ang user_levels table bago ang UserSeeder.
        $this->call([
            UserLevelSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            InventorySeeder::class,
            BarangaySeeder::class,
            PatientRecordsSeeder::class
        ]);
        
        // \App\Models\User::factory(10)->create();
    }
}