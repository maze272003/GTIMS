<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserLevel; // Import mo 'to

class UserLevelSeeder extends Seeder
{
    public function run(): void
    {
        UserLevel::create(['name' => 'superadmin']);
        UserLevel::create(['name' => 'admin']);
        UserLevel::create(['name' => 'encoder']);
        UserLevel::create(['name' => 'doctor']);
        UserLevel::create(['name' => 'mayor']);
        UserLevel::create(['name' => 'finance']);
        // ... Magdagdag pa kung kailangan
    }
}