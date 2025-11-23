<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // Import ang User model
use App\Models\Branch;
use Illuminate\Support\Facades\Hash; // Import ang Hash para sa password

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Superadmin
        User::create([
            'name' => 'Super Admin',
            'email' => 'jmjonatas4@gmail.com',
            'branch_id' => 1,
            'password' => Hash::make('password'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);

        // 1. Superadmin
        User::create([
            'name' => 'Super Admin',
            'email' => 'pescojohnanthony@gmail.com',
            'branch_id' => 1,
            'password' => Hash::make('password'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);
        
        // 2. Superadmin
        User::create([
            'name' => 'Sigrae Super Duper',
            'email' => 'sde.gabriel.77@gmail.com',
            'branch_id' => 1,
            'branch_id' => 1,
            'password' => Hash::make('12345678'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);
        // 2. Superadmin
        User::create([
            'name' => 'Ace',
            'email' => 'acepadillaace@gmail.com',
            'branch_id' => 1,
            'branch_id' => 1,
            'password' => Hash::make('12345678'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);

        // 2. Admin
        User::create([
            'name' => 'Pharmacist (RHU 1)',
            'email' => 'johnmichaeljonatas71@gmail.com',
            'branch_id' => 1,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'user_level_id' => 2, // ID para sa 'admin'
        ]);
        User::create([
            'name' => 'Pharmacist (RHU 2)',
            'email' => 'johnmichaeljonatas72@gmail.com',
            'branch_id' => 1,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'user_level_id' => 2, // ID para sa 'admin'
        ]);

        // 3. Encoder
        User::create([
            'name' => 'Staff',
            'name' => 'Staff',
            'email' => 'jg.jonatas.au@phinmaed.com',
            'branch_id' => 2,
            'branch_id' => 2,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'user_level_id' => 3, // ID para sa 'encoder'
        ]);
        User::create([
            'name' => 'Doctor User',
            'branch_id' => 1,
            'email' => 'doctor@example.com', // Palitan ito ng totoong email
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'user_level_id' => 4, // ID para sa 'doctor'
        ]);
    }
}