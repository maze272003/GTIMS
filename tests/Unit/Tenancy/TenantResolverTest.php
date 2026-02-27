<?php

namespace Tests\Unit\Tenancy;

use App\Models\Barangay;
use App\Models\Province;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolverTest extends TestCase
{
    use RefreshDatabase;

    protected TenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TenantResolver();
    }

    public function test_from_slugs_resolves_valid_province_and_barangay(): void
    {
        $province = Province::factory()->create(['slug' => 'bulacan', 'is_active' => true]);
        $barangay = Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'malolos',
            'is_active' => true,
        ]);

        $ctx = $this->resolver->fromSlugs('bulacan', 'malolos');

        $this->assertNotNull($ctx);
        $this->assertTrue($ctx->isBarangay());
        $this->assertEquals($province->id, $ctx->provinceId);
        $this->assertEquals($barangay->id, $ctx->barangayId);
        $this->assertEquals('bulacan', $ctx->provinceSlug);
        $this->assertEquals('malolos', $ctx->barangaySlug);
    }

    public function test_from_slugs_resolves_case_insensitive_and_preserves_canonical_slugs(): void
    {
        $province = Province::factory()->create(['slug' => 'bulacan', 'is_active' => true]);
        $barangay = Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'malolos',
            'is_active' => true,
        ]);

        $ctx = $this->resolver->fromSlugs('BULACAN', 'MALOLOS');

        $this->assertNotNull($ctx);
        $this->assertEquals('bulacan', $ctx->provinceSlug);
        $this->assertEquals('malolos', $ctx->barangaySlug);
        $this->assertEquals($barangay->id, $ctx->barangayId);
    }

    public function test_from_slugs_rejects_reserved_moderator_prefix(): void
    {
        $ctx = $this->resolver->fromSlugs('moderator', 'tenant-a');

        $this->assertNull($ctx);
    }

    public function test_from_slugs_returns_null_for_invalid_province(): void
    {
        $ctx = $this->resolver->fromSlugs('nonexistent', 'malolos');

        $this->assertNull($ctx);
    }

    public function test_from_slugs_returns_null_for_invalid_barangay(): void
    {
        Province::factory()->create(['slug' => 'bulacan', 'is_active' => true]);

        $ctx = $this->resolver->fromSlugs('bulacan', 'nonexistent');

        $this->assertNull($ctx);
    }

    public function test_from_slugs_returns_null_for_inactive_province(): void
    {
        $province = Province::factory()->create(['slug' => 'inactive-prov', 'is_active' => false]);
        Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'brgy-test',
            'is_active' => true,
        ]);

        $ctx = $this->resolver->fromSlugs('inactive-prov', 'brgy-test');

        $this->assertNull($ctx);
    }

    public function test_from_slugs_returns_null_for_inactive_barangay(): void
    {
        $province = Province::factory()->create(['slug' => 'bulacan', 'is_active' => true]);
        Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'inactive-brgy',
            'is_active' => false,
        ]);

        $ctx = $this->resolver->fromSlugs('bulacan', 'inactive-brgy');

        $this->assertNull($ctx);
    }

    public function test_from_province_slug_resolves_province(): void
    {
        $province = Province::factory()->create(['slug' => 'pampanga', 'is_active' => true]);

        $ctx = $this->resolver->fromProvinceSlug('pampanga');

        $this->assertNotNull($ctx);
        $this->assertTrue($ctx->isProvince());
        $this->assertEquals($province->id, $ctx->provinceId);
        $this->assertNull($ctx->barangayId);
    }

    public function test_from_province_slug_returns_null_for_nonexistent(): void
    {
        $ctx = $this->resolver->fromProvinceSlug('does-not-exist');

        $this->assertNull($ctx);
    }

    public function test_user_has_platform_membership(): void
    {
        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'platform',
            'scope_id' => null,
            'status' => 'active',
        ]);

        $ctx = TenantContext::platform();

        $this->assertTrue($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_platform_user_can_access_any_tenant(): void
    {
        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'platform',
            'scope_id' => null,
            'status' => 'active',
        ]);

        $province = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province->id]);
        $ctx = TenantContext::forBarangay($province, $barangay);

        $this->assertTrue($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_province_user_can_access_barangay_in_their_province(): void
    {
        $province = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province->id]);

        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'province',
            'scope_id' => $province->id,
            'status' => 'active',
        ]);

        $ctx = TenantContext::forBarangay($province, $barangay);

        $this->assertTrue($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_barangay_user_can_access_their_barangay(): void
    {
        $province = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province->id]);

        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'barangay',
            'scope_id' => $barangay->id,
            'status' => 'active',
        ]);

        $ctx = TenantContext::forBarangay($province, $barangay);

        $this->assertTrue($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_barangay_user_cannot_access_other_barangay(): void
    {
        $province = Province::factory()->create();
        $barangay1 = Barangay::factory()->create(['province_id' => $province->id]);
        $barangay2 = Barangay::factory()->create(['province_id' => $province->id]);

        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'barangay',
            'scope_id' => $barangay1->id,
            'status' => 'active',
        ]);

        $ctx = TenantContext::forBarangay($province, $barangay2);

        $this->assertFalse($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_province_user_cannot_access_other_province(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province2->id]);

        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'province',
            'scope_id' => $province1->id,
            'status' => 'active',
        ]);

        $ctx = TenantContext::forBarangay($province2, $barangay);

        $this->assertFalse($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_suspended_membership_denies_access(): void
    {
        $province = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province->id]);

        $user = $this->createUser();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'scope_type' => 'barangay',
            'scope_id' => $barangay->id,
            'status' => 'suspended',
        ]);

        $ctx = TenantContext::forBarangay($province, $barangay);

        $this->assertFalse($this->resolver->userHasMembership($user, $ctx));
    }

    public function test_user_without_membership_is_denied(): void
    {
        $province = Province::factory()->create();
        $barangay = Barangay::factory()->create(['province_id' => $province->id]);

        $user = $this->createUser();

        $ctx = TenantContext::forBarangay($province, $barangay);

        $this->assertFalse($this->resolver->userHasMembership($user, $ctx));
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }
}
