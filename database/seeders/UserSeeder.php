<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // Import ang User model
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
            'password' => Hash::make('password'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);

        // 1. Superadmin
        User::create([
            'name' => 'Super Admin',
            'email' => 'pescojohnanthony@gmail.com',
            'password' => Hash::make('password'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);
        
        // 2. Superadmin
        User::create([
            'name' => 'Sigrae Super Duper',
            'email' => 'sde.gabriel.77@gmail.com',
            'password' => Hash::make('12345678'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);
        // 2. Superadmin
        User::create([
            'name' => 'Ace',
            'email' => 'acepadillaace@gmail.com',
            'password' => Hash::make('12345678'), // Awtomatikong na-hash
            'email_verified_at' => now(), // Set as verified na
            'user_level_id' => 1, // ID para sa 'superadmin'
        ]);

        // 2. Admin
        User::create([
            'name' => 'Admin User',
            'email' => 'johnmichaeljonatas72@gmail.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'user_level_id' => 2, // ID para sa 'admin'
        ]);

        // 3. Encoder
        User::create([
            'name' => 'Encoder User',
            'email' => 'jg.jonatas.au@phinmaed.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'user_level_id' => 3, // ID para sa 'encoder'
        ]);
    }
}