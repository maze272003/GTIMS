<?php

namespace Tests\Unit\Tenancy;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Province;
use App\Models\Barangay;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopedTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_tenant_scope_filters_by_province_in_province_context(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();
        $branch = Branch::create(['name' => 'Test']);
        $product = Product::factory()->create();

        // Create inventories in different provinces
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province1->id,
            'barangay_id' => null,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province2->id,
            'barangay_id' => null,
        ]);

        $ctx = TenantContext::forProvince($province1);
        $result = Inventory::forTenant($ctx)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($province1->id, $result->first()->province_id);
    }

    public function test_for_tenant_scope_filters_by_barangay_in_barangay_context(): void
    {
        $province = Province::factory()->create();
        $barangay1 = Barangay::factory()->create(['province_id' => $province->id]);
        $barangay2 = Barangay::factory()->create(['province_id' => $province->id]);
        $branch = Branch::create(['name' => 'Test']);
        $product = Product::factory()->create();

        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province->id,
            'barangay_id' => $barangay1->id,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province->id,
            'barangay_id' => $barangay2->id,
        ]);

        $ctx = TenantContext::forBarangay($province, $barangay1);
        $result = Inventory::forTenant($ctx)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($barangay1->id, $result->first()->barangay_id);
    }

    public function test_platform_context_returns_all_records(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();
        $branch = Branch::create(['name' => 'Test']);
        $product = Product::factory()->create();

        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province1->id,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province2->id,
        ]);

        $ctx = TenantContext::platform();
        $result = Inventory::forTenant($ctx)->get();

        $this->assertCount(2, $result);
    }

    public function test_for_province_scope_works_directly(): void
    {
        $province = Province::factory()->create();
        $branch = Branch::create(['name' => 'Test']);
        $product = Product::factory()->create();

        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province->id,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $province->id + 999,
        ]);

        $result = Inventory::forProvince($province->id)->get();

        $this->assertCount(1, $result);
    }
}
