<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Province;
use App\Models\RoleAssignment;
use App\Models\TenantMembership;
use App\Models\TenantRole;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ModeratorSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->first() ?? Branch::query()->create(['name' => 'Main Branch']);
        $defaultProvinceId = Province::query()->value('id');
        $superadminLevel = UserLevel::query()->firstOrCreate(['name' => 'superadmin']);

        $email = (string) config('tenancy.seeder.moderator_email', 'moderator@gtims.local');
        $name = (string) config('tenancy.seeder.moderator_name', 'GTIMS Moderator');
        $password = (string) config('tenancy.seeder.default_password', 'password');

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->branch_id = $branch->id;
        $user->user_level_id = $superadminLevel->id;
        $user->province_id = $user->province_id ?: $defaultProvinceId;
        $user->barangay_id = null;
        $user->email_verified_at = $user->email_verified_at ?? now();
        if (!$user->exists || empty($user->password)) {
            $user->password = Hash::make($password);
        }
        $user->save();

        TenantMembership::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'scope_type' => 'platform',
                'scope_id' => null,
            ],
            [
                'is_primary' => true,
                'status' => 'active',
            ]
        );

        $moderatorRole = TenantRole::query()->where('slug', (string) config('tenancy.roles.moderator.slug', 'moderator'))->first();
        if ($moderatorRole) {
            RoleAssignment::query()->firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $moderatorRole->id,
                'scope_type' => 'platform',
                'scope_id' => null,
            ]);
        }
    }
}
