<?php

namespace Tests\Unit\Tenancy;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Province;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_applies_province_and_barangay_filters(): void
    {
        $provinceA = Province::factory()->create(['slug' => 'alpha']);
        $provinceB = Province::factory()->create(['slug' => 'beta']);
        $barangayA1 = Barangay::factory()->create(['province_id' => $provinceA->id, 'slug' => 'a-1']);
        $barangayA2 = Barangay::factory()->create(['province_id' => $provinceA->id, 'slug' => 'a-2']);
        $barangayB1 = Barangay::factory()->create(['province_id' => $provinceB->id, 'slug' => 'b-1']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        $invA1 = Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $provinceA->id,
            'barangay_id' => $barangayA1->id,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $provinceA->id,
            'barangay_id' => $barangayA2->id,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'province_id' => $provinceB->id,
            'barangay_id' => $barangayB1->id,
        ]);

        $ctx = TenantContext::forBarangay($provinceA, $barangayA1);

        $ids = TenantScope::apply(Inventory::query(), $ctx)->pluck('id')->all();

        $this->assertSame([$invA1->id], $ids);
    }
}

