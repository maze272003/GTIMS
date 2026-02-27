<?php

namespace Tests\Unit\Tenancy;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Province;
use App\Models\RoleAssignment;
use App\Models\TenantRole;
use App\Models\User;
use App\Models\UserLevel;
use App\Tenancy\ScopedPermissionResolver;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopedPermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_scoped_role_allows_permission_in_any_tenant(): void
    {
        $user = $this->createUser();
        $permission = Permission::create(['name' => 'dashboard.view', 'group' => 'Dashboard']);
        $role = TenantRole::create([
            'name' => 'Moderator',
            'slug' => 'moderator',
            'scope_type' => 'platform',
            'is_system_role' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'platform',
            'scope_id' => null,
        ]);

        $province = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province->id]);
        $ctx = TenantContext::forBarangay($province, $barangay);

        $resolver = app(ScopedPermissionResolver::class);

        $this->assertTrue($resolver->hasPermission($user, 'dashboard.view', $ctx));
    }

    public function test_province_scoped_role_is_limited_to_same_province(): void
    {
        $user = $this->createUser();
        $permission = Permission::create(['name' => 'reports.view', 'group' => 'Reports']);
        $role = TenantRole::create([
            'name' => 'Province Admin',
            'slug' => 'province-admin',
            'scope_type' => 'province',
            'is_system_role' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();
        $barangayA = Barangay::factory()->create(['province_id' => $provinceA->id]);
        $barangayB = Barangay::factory()->create(['province_id' => $provinceB->id]);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'province',
            'scope_id' => $provinceA->id,
        ]);

        $resolver = app(ScopedPermissionResolver::class);

        $ctxA = TenantContext::forBarangay($provinceA, $barangayA);
        $ctxB = TenantContext::forBarangay($provinceB, $barangayB);

        $this->assertTrue($resolver->hasPermission($user, 'reports.view', $ctxA));
        $this->assertFalse($resolver->hasPermission($user, 'reports.view', $ctxB));
    }

    public function test_legacy_user_level_permissions_are_used_as_fallback(): void
    {
        $level = UserLevel::create(['name' => 'admin']);
        $permission = Permission::create(['name' => 'inventory.view', 'group' => 'Inventory']);
        $level->permissions()->sync([$permission->id]);

        $user = $this->createUser($level);
        $resolver = app(ScopedPermissionResolver::class);

        $this->assertTrue($resolver->hasPermission($user, 'inventory.view'));
    }

    protected function createUser(?UserLevel $level = null): User
    {
        $branch = Branch::create(['name' => 'Head Office']);

        return User::factory()->create([
            'branch_id' => $branch->id,
            'email_verified_at' => now(),
            'user_level_id' => $level?->id,
        ]);
    }
}

