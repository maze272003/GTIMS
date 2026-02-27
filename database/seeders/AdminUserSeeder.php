<?php

namespace Database\Seeders;

use App\Models\Barangay;
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

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->first() ?? Branch::query()->create(['name' => 'Main Branch']);
        $adminLevel = UserLevel::query()->firstOrCreate(['name' => 'admin']);
        $encoderLevel = UserLevel::query()->firstOrCreate(['name' => 'encoder']);
        $password = (string) config('tenancy.seeder.default_password', 'password');
        $barangaysPerProvince = max(1, (int) config('tenancy.seeder.demo_barangays_per_province', 5));

        $barangayAdminRole = TenantRole::query()
            ->where('slug', (string) config('tenancy.roles.barangay_admin.slug', 'barangay-admin'))
            ->first();

        $provinceSlugs = (array) config('tenancy.seeder.demo_provinces', ['bulacan', 'cebu', 'davao-del-sur']);
        foreach ($provinceSlugs as $rawSlug) {
            $slug = Str::slug((string) $rawSlug);
            if ($slug === '') {
                continue;
            }

            $province = Province::query()->where('slug', $slug)->first();
            if (!$province) {
                continue;
            }

            $barangays = Barangay::query()
                ->where('province_id', $province->id)
                ->whereNotNull('slug')
                ->where('is_active', true)
                ->orderBy('id')
                ->take($barangaysPerProvince)
                ->get();

            foreach ($barangays as $barangay) {
                $this->seedBarangayAdmin($province, $barangay, $branch->id, $adminLevel->id, $password, $barangayAdminRole?->id);
                $this->seedBarangayStaff($province, $barangay, $branch->id, $encoderLevel->id, $password);
            }
        }
    }

    protected function seedBarangayAdmin(
        Province $province,
        Barangay $barangay,
        int $branchId,
        int $userLevelId,
        string $password,
        ?int $roleId
    ): void {
        $email = "admin+{$province->slug}-{$barangay->id}@gtims.local";

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = 'Admin - ' . $barangay->barangay_name;
        $user->branch_id = $branchId;
        $user->user_level_id = $userLevelId;
        $user->province_id = $province->id;
        $user->barangay_id = $barangay->id;
        $user->email_verified_at = $user->email_verified_at ?? now();
        if (!$user->exists || empty($user->password)) {
            $user->password = Hash::make($password);
        }
        $user->save();

        TenantMembership::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'scope_type' => 'barangay',
                'scope_id' => $barangay->id,
            ],
            [
                'is_primary' => true,
                'status' => 'active',
            ]
        );

        if ($roleId) {
            RoleAssignment::query()->firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'scope_type' => 'barangay',
                'scope_id' => $barangay->id,
            ]);
        }
    }

    protected function seedBarangayStaff(
        Province $province,
        Barangay $barangay,
        int $branchId,
        int $userLevelId,
        string $password
    ): void {
        $email = "staff+{$province->slug}-{$barangay->id}@gtims.local";

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = 'Staff - ' . $barangay->barangay_name;
        $user->branch_id = $branchId;
        $user->user_level_id = $userLevelId;
        $user->province_id = $province->id;
        $user->barangay_id = $barangay->id;
        $user->email_verified_at = $user->email_verified_at ?? now();
        if (!$user->exists || empty($user->password)) {
            $user->password = Hash::make($password);
        }
        $user->save();

        TenantMembership::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'scope_type' => 'barangay',
                'scope_id' => $barangay->id,
            ],
            [
                'is_primary' => true,
                'status' => 'active',
            ]
        );
    }
}

