<?php

namespace Tests\Feature\Tenancy;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Province;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithDashboardPermission(string $levelName = 'admin'): User
    {
        $level = UserLevel::create(['name' => $levelName]);
        $permission = Permission::firstOrCreate([
            'name' => 'dashboard.view',
        ], [
            'group' => 'Dashboard',
            'description' => 'View dashboard',
        ]);
        $level->permissions()->syncWithoutDetaching([$permission->id]);

        $branch = Branch::factory()->create();

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_moderator_can_access_multiple_tenant_dashboards(): void
    {
        $provinceA = Province::factory()->create(['slug' => 'prov-a', 'is_active' => true]);
        $provinceB = Province::factory()->create(['slug' => 'prov-b', 'is_active' => true]);
        $barangayA = Barangay::factory()->create(['province_id' => $provinceA->id, 'slug' => 'a-main', 'is_active' => true]);
        $barangayB = Barangay::factory()->create(['province_id' => $provinceB->id, 'slug' => 'b-main', 'is_active' => true]);

        $moderator = $this->createUserWithDashboardPermission('superadmin');
        TenantMembership::factory()->platform()->create(['user_id' => $moderator->id, 'status' => 'active']);

        $responseA = $this->actingAs($moderator)->get("/{$provinceA->slug}/{$barangayA->slug}/dashboard");
        $responseB = $this->actingAs($moderator)->get("/{$provinceB->slug}/{$barangayB->slug}/dashboard");

        $this->assertNotEquals(403, $responseA->status());
        $this->assertNotEquals(403, $responseB->status());
        $this->assertNotEquals(404, $responseA->status());
        $this->assertNotEquals(404, $responseB->status());
    }

    public function test_province_admin_is_blocked_from_other_provinces(): void
    {
        $provinceA = Province::factory()->create(['slug' => 'prov-a', 'is_active' => true]);
        $provinceB = Province::factory()->create(['slug' => 'prov-b', 'is_active' => true]);
        $barangayA = Barangay::factory()->create(['province_id' => $provinceA->id, 'slug' => 'a-main', 'is_active' => true]);
        $barangayB = Barangay::factory()->create(['province_id' => $provinceB->id, 'slug' => 'b-main', 'is_active' => true]);

        $provinceUser = $this->createUserWithDashboardPermission();
        TenantMembership::factory()->province($provinceA->id)->create(['user_id' => $provinceUser->id, 'status' => 'active']);

        $allowed = $this->actingAs($provinceUser)->get("/{$provinceA->slug}/{$barangayA->slug}/dashboard");
        $blocked = $this->actingAs($provinceUser)->get("/{$provinceB->slug}/{$barangayB->slug}/dashboard");

        $this->assertNotEquals(403, $allowed->status());
        $blocked->assertStatus(403);
    }

    public function test_barangay_admin_is_blocked_from_other_barangays_in_same_province(): void
    {
        $province = Province::factory()->create(['slug' => 'prov-a', 'is_active' => true]);
        $barangayA = Barangay::factory()->create(['province_id' => $province->id, 'slug' => 'a-main', 'is_active' => true]);
        $barangayB = Barangay::factory()->create(['province_id' => $province->id, 'slug' => 'a-other', 'is_active' => true]);

        $barangayUser = $this->createUserWithDashboardPermission();
        TenantMembership::factory()->barangay($barangayA->id)->create(['user_id' => $barangayUser->id, 'status' => 'active']);

        $allowed = $this->actingAs($barangayUser)->get("/{$province->slug}/{$barangayA->slug}/dashboard");
        $blocked = $this->actingAs($barangayUser)->get("/{$province->slug}/{$barangayB->slug}/dashboard");

        $this->assertNotEquals(403, $allowed->status());
        $blocked->assertStatus(403);
    }
}

