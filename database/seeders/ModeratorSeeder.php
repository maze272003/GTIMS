<?php

namespace Database\Seeders;

use App\Models\Moderator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ModeratorSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('tenancy.seeder.moderator_email', 'moderator@gtims.local');
        $name = (string) config('tenancy.seeder.moderator_name', 'GTIMS Moderator');
        $password = (string) config('tenancy.seeder.default_password', 'password');

        $moderator = Moderator::query()->firstOrNew(['email' => $email]);
        $moderator->name = $name;
        $moderator->email_verified_at = $moderator->email_verified_at ?? now();
        if (!$moderator->exists || empty($moderator->password)) {
            $moderator->password = Hash::make($password);
        }
        $moderator->save();
    }
}
