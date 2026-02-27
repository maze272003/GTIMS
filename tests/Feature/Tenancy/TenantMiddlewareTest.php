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

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;
    private Barangay $barangay;
    private User $user;
    private UserLevel $level;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'Test Branch']);

        $perm = Permission::create(['name' => 'dashboard.view', 'group' => 'Dashboard']);
        $this->level->permissions()->sync([$perm->id]);

        $this->province = Province::factory()->create([
            'slug' => 'bulacan',
            'is_active' => true,
        ]);

        $this->barangay = Barangay::factory()->create([
            'province_id' => $this->province->id,
            'slug' => 'malolos',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $this->level->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_tenant_route_resolves_valid_slugs(): void
    {
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'barangay',
            'scope_id' => $this->barangay->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/bulacan/malolos/dashboard");

        // Should not get 404 (tenant resolved) or 403 (membership valid)
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_non_canonical_slug_redirects_to_canonical_route(): void
    {
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'barangay',
            'scope_id' => $this->barangay->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/BULACAN/MALOLOS/dashboard');

        $response->assertRedirect('/bulacan/malolos/dashboard');
        $response->assertStatus(301);
    }

    public function test_tenant_route_returns_404_for_invalid_province_slug(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/nonexistent/malolos/dashboard");

        $response->assertStatus(404);
    }

    public function test_tenant_route_returns_404_for_invalid_barangay_slug(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/bulacan/nonexistent/dashboard");

        $response->assertStatus(404);
    }

    public function test_reserved_moderator_prefix_cannot_be_used_as_tenant_slug(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/moderator/tenant-a/dashboard');

        $response->assertStatus(404);
    }

    public function test_tenant_route_returns_404_for_inactive_province(): void
    {
        $inactiveProvince = Province::factory()->create([
            'slug' => 'inactive-prov',
            'is_active' => false,
        ]);
        Barangay::factory()->create([
            'province_id' => $inactiveProvince->id,
            'slug' => 'some-brgy',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/inactive-prov/some-brgy/dashboard");

        $response->assertStatus(404);
    }

    public function test_tenant_route_returns_403_when_user_has_no_membership(): void
    {
        // User has no membership for this tenant
        $response = $this->actingAs($this->user)
            ->get("/bulacan/malolos/dashboard");

        $response->assertStatus(403);
    }

    public function test_tenant_route_returns_403_for_suspended_membership(): void
    {
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'barangay',
            'scope_id' => $this->barangay->id,
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/bulacan/malolos/dashboard");

        $response->assertStatus(403);
    }

    public function test_platform_user_can_access_any_tenant(): void
    {
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'platform',
            'scope_id' => null,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/bulacan/malolos/dashboard");

        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_province_user_can_access_barangay_in_their_province(): void
    {
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'province',
            'scope_id' => $this->province->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/bulacan/malolos/dashboard");

        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_cross_tenant_access_is_blocked(): void
    {
        // Create another province with barangay
        $otherProvince = Province::factory()->create([
            'slug' => 'pampanga',
            'is_active' => true,
        ]);
        $otherBarangay = Barangay::factory()->create([
            'province_id' => $otherProvince->id,
            'slug' => 'san-fernando',
            'is_active' => true,
        ]);

        // User has membership in bulacan/malolos only
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'barangay',
            'scope_id' => $this->barangay->id,
            'status' => 'active',
        ]);

        // Try to access pampanga/san-fernando
        $response = $this->actingAs($this->user)
            ->get("/pampanga/san-fernando/dashboard");

        $response->assertStatus(403);
    }

    public function test_cross_barangay_access_within_same_province_is_blocked(): void
    {
        $otherBarangay = Barangay::factory()->create([
            'province_id' => $this->province->id,
            'slug' => 'meycauayan',
            'is_active' => true,
        ]);

        // User has membership only in malolos
        TenantMembership::factory()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'barangay',
            'scope_id' => $this->barangay->id,
            'status' => 'active',
        ]);

        // Try to access meycauayan in the same province
        $response = $this->actingAs($this->user)
            ->get("/bulacan/meycauayan/dashboard");

        $response->assertStatus(403);
    }

    public function test_tenant_login_page_renders_with_valid_slugs(): void
    {
        $response = $this->get("/bulacan/malolos/login");

        // Should resolve tenant and render login page
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_tenant_login_returns_404_for_invalid_slugs(): void
    {
        $response = $this->get("/invalid/slugs/login");

        $response->assertStatus(404);
    }

    public function test_moderator_login_page_renders(): void
    {
        $response = $this->get("/moderator/login");

        $response->assertStatus(200);
    }

    public function test_tenant_forgot_password_page_renders_with_valid_slugs(): void
    {
        $response = $this->get('/bulacan/malolos/forgot-password');

        $response->assertStatus(200);
    }

    public function test_moderator_forgot_password_page_renders(): void
    {
        $response = $this->get('/moderator/forgot-password');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_redirected_from_tenant_route(): void
    {
        $response = $this->get("/bulacan/malolos/dashboard");

        // Should redirect to login
        $response->assertRedirect();
    }
}
