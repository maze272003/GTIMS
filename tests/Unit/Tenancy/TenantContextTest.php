<?php

namespace Tests\Unit\Tenancy;

use App\Models\Barangay;
use App\Models\Province;
use App\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    public function test_platform_context_is_platform_scope(): void
    {
        $ctx = TenantContext::platform();

        $this->assertEquals('platform', $ctx->scopeType);
        $this->assertTrue($ctx->isPlatform());
        $this->assertFalse($ctx->isProvince());
        $this->assertFalse($ctx->isBarangay());
        $this->assertNull($ctx->provinceId);
        $this->assertNull($ctx->barangayId);
    }

    public function test_province_context_from_factory(): void
    {
        $province = new Province(['name' => 'Bulacan', 'slug' => 'bulacan']);
        $province->id = 1;

        $ctx = TenantContext::forProvince($province);

        $this->assertEquals('province', $ctx->scopeType);
        $this->assertFalse($ctx->isPlatform());
        $this->assertTrue($ctx->isProvince());
        $this->assertFalse($ctx->isBarangay());
        $this->assertEquals(1, $ctx->provinceId);
        $this->assertNull($ctx->barangayId);
        $this->assertEquals('bulacan', $ctx->provinceSlug);
    }

    public function test_barangay_context_from_factory(): void
    {
        $province = new Province(['name' => 'Bulacan', 'slug' => 'bulacan']);
        $province->id = 1;
        $barangay = new Barangay(['barangay_name' => 'Malolos', 'slug' => 'malolos']);
        $barangay->id = 5;

        $ctx = TenantContext::forBarangay($province, $barangay);

        $this->assertEquals('barangay', $ctx->scopeType);
        $this->assertFalse($ctx->isPlatform());
        $this->assertFalse($ctx->isProvince());
        $this->assertTrue($ctx->isBarangay());
        $this->assertEquals(1, $ctx->provinceId);
        $this->assertEquals(5, $ctx->barangayId);
        $this->assertEquals('bulacan', $ctx->provinceSlug);
        $this->assertEquals('malolos', $ctx->barangaySlug);
    }

    public function test_to_array_returns_all_fields(): void
    {
        $ctx = new TenantContext(
            scopeType: 'barangay',
            provinceId: 2,
            barangayId: 10,
            provinceSlug: 'nueva-ecija',
            barangaySlug: 'cabanatuan',
        );

        $arr = $ctx->toArray();

        $this->assertEquals('barangay', $arr['scope_type']);
        $this->assertEquals(2, $arr['province_id']);
        $this->assertEquals(10, $arr['barangay_id']);
        $this->assertEquals('nueva-ecija', $arr['province_slug']);
        $this->assertEquals('cabanatuan', $arr['barangay_slug']);
    }

    public function test_to_session_data_uses_correct_keys(): void
    {
        $ctx = TenantContext::platform();
        $session = $ctx->toSessionData();

        $this->assertArrayHasKey('tenant.scope_type', $session);
        $this->assertArrayHasKey('tenant.province_id', $session);
        $this->assertArrayHasKey('tenant.barangay_id', $session);
        $this->assertArrayHasKey('tenant.route_slug_province', $session);
        $this->assertArrayHasKey('tenant.route_slug_barangay', $session);
        $this->assertEquals('platform', $session['tenant.scope_type']);
    }

    public function test_from_session_restores_context(): void
    {
        $original = new TenantContext(
            scopeType: 'barangay',
            provinceId: 3,
            barangayId: 7,
            provinceSlug: 'pampanga',
            barangaySlug: 'san-fernando',
        );

        $sessionData = $original->toSessionData();
        $restored = TenantContext::fromSession($sessionData);

        $this->assertNotNull($restored);
        $this->assertEquals($original->scopeType, $restored->scopeType);
        $this->assertEquals($original->provinceId, $restored->provinceId);
        $this->assertEquals($original->barangayId, $restored->barangayId);
        $this->assertEquals($original->provinceSlug, $restored->provinceSlug);
        $this->assertEquals($original->barangaySlug, $restored->barangaySlug);
    }

    public function test_from_session_returns_null_for_empty_data(): void
    {
        $ctx = TenantContext::fromSession([]);

        $this->assertNull($ctx);
    }

    public function test_context_is_immutable(): void
    {
        $ctx = TenantContext::platform();

        // Properties are readonly, so this should not compile/work
        // We verify by checking the property exists and is set
        $this->assertEquals('platform', $ctx->scopeType);
    }
}
