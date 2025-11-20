<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    public function run()
    {
        DB::table('branches')->insert([
            [
                'id' => 1,
                'name' => 'RHU 1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'RHU 2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // nag add ako neto para sa superadmin baka kaylanganin nyo kung sakali..
            [
                'id' => 3,
                'name' => 'Multi-Branchs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}