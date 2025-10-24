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
        // ... Magdagdag pa kung kailangan
    }
}