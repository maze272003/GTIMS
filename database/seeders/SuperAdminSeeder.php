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
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->first() ?? Branch::query()->create(['name' => 'Main Branch']);
        $superadminLevel = UserLevel::query()->firstOrCreate(['name' => 'superadmin']);
        $password = (string) config('tenancy.seeder.default_password', 'password');

        $provinceAdminRole = TenantRole::query()
            ->where('slug', (string) config('tenancy.roles.province_admin.slug', 'province-admin'))
            ->first();

        $provinceSlugs = (array) config('tenancy.seeder.demo_provinces', ['bulacan', 'cebu', 'davao-del-sur']);
        foreach ($provinceSlugs as $index => $rawSlug) {
            $slug = Str::slug((string) $rawSlug);
            if ($slug === '') {
                continue;
            }

            $province = Province::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::title(str_replace('-', ' ', $slug)),
                    'code' => strtoupper(sprintf('DM-%03d', $index + 1)),
                    'is_active' => true,
                    'settings_json' => null,
                ]
            );

            if (!$province->is_active) {
                $province->forceFill(['is_active' => true])->save();
            }

            $email = "superadmin+{$slug}@gtims.local";
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = 'Super Admin - ' . $province->name;
            $user->branch_id = $branch->id;
            $user->user_level_id = $superadminLevel->id;
            $user->province_id = $province->id;
            $user->barangay_id = null;
            $user->email_verified_at = $user->email_verified_at ?? now();
            if (!$user->exists || empty($user->password)) {
                $user->password = Hash::make($password);
            }
            $user->save();

            TenantMembership::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'scope_type' => 'province',
                    'scope_id' => $province->id,
                ],
                [
                    'is_primary' => true,
                    'status' => 'active',
                ]
            );

            if ($provinceAdminRole) {
                RoleAssignment::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => $provinceAdminRole->id,
                    'scope_type' => 'province',
                    'scope_id' => $province->id,
                ]);
            }
        }
    }
}

