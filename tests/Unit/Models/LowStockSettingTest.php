<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LowStockSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_threshold_fallback()
    {
        // No settings exist, should return default 10
        $this->assertEquals(10, LowStockSetting::getThresholdFor(999));
    }

    public function test_global_threshold_used_when_set()
    {
        LowStockSetting::create(['threshold' => 25, 'is_global' => true]);
        $this->assertEquals(25, LowStockSetting::getThresholdFor(999));
    }

    public function test_product_specific_override()
    {
        LowStockSetting::create(['threshold' => 25, 'is_global' => true]);
        
        $product = Product::factory()->create();
        LowStockSetting::create(['product_id' => $product->id, 'threshold' => 50, 'is_global' => false]);

        $this->assertEquals(50, LowStockSetting::getThresholdFor($product->id));
    }

    public function test_product_branch_specific_override()
    {
        LowStockSetting::create(['threshold' => 25, 'is_global' => true]);
        
        $product = Product::factory()->create();
        $branch = Branch::create(['name' => 'RHU 1']);
        
        LowStockSetting::create(['product_id' => $product->id, 'threshold' => 50, 'is_global' => false]);
        LowStockSetting::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'threshold' => 75,
            'is_global' => false,
        ]);

        $this->assertEquals(75, LowStockSetting::getThresholdFor($product->id, $branch->id));
    }
}
