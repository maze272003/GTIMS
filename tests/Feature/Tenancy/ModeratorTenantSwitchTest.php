<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Province;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorTenantSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function moderatorUser(): User
    {
        $level = UserLevel::create(['name' => 'superadmin']);
        $permission = Permission::firstOrCreate([
            'name' => 'dashboard.view',
        ], [
            'group' => 'Dashboard',
            'description' => 'View dashboard',
        ]);
        $level->permissions()->syncWithoutDetaching([$permission->id]);
        $branch = Branch::factory()->create();

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);

        TenantMembership::factory()->platform()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_moderator_switches_tenant_and_audit_event_is_recorded(): void
    {
        $province = Province::factory()->create(['slug' => 'bulacan', 'is_active' => true]);
        $barangay = Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'malolos',
            'is_active' => true,
        ]);
        $moderator = $this->moderatorUser();

        $response = $this->actingAs($moderator)->post(route('moderator.switch'), [
            'province_slug' => $province->slug,
            'barangay_slug' => $barangay->slug,
        ]);

        $response->assertRedirect("/{$province->slug}/{$barangay->slug}/dashboard");
        $this->assertEquals($province->id, session('tenant.province_id'));
        $this->assertEquals($barangay->id, session('tenant.barangay_id'));
        $this->assertEquals('barangay', session('tenant.scope_type'));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'moderator.tenant_switch',
            'user_id' => $moderator->id,
            'province_id' => $province->id,
            'barangay_id' => $barangay->id,
        ]);
    }

    public function test_moderator_can_switch_back_to_platform_and_session_is_cleared(): void
    {
        $moderator = $this->moderatorUser();
        session([
            'tenant.scope_type' => 'barangay',
            'tenant.province_id' => 1,
            'tenant.barangay_id' => 2,
            'tenant.route_slug_province' => 'a',
            'tenant.route_slug_barangay' => 'b',
        ]);

        $response = $this->actingAs($moderator)->post(route('moderator.switch.platform'));

        $response->assertRedirect(route('moderator.dashboard'));
        $this->assertNull(session('tenant.scope_type'));
        $this->assertNull(session('tenant.province_id'));
        $this->assertNull(session('tenant.barangay_id'));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'moderator.platform_switch',
            'user_id' => $moderator->id,
        ]);
    }
}

