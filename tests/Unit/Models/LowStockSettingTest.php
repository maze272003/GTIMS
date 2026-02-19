<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Inventory;
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

    public function test_aggregated_stock_sums_multiple_batches()
    {
        $product = Product::factory()->create();
        $branch = Branch::create(['name' => 'RHU 1']);

        // Create multiple batches for the same product at same branch
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 15,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-002',
            'quantity' => 25,
            'expiry_date' => now()->addMonths(12),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-003',
            'quantity' => 10,
            'expiry_date' => now()->addMonths(3),
            'is_archived' => false,
        ]);

        // Total should be 15 + 25 + 10 = 50
        $total = LowStockSetting::getAggregatedStockForBranch($product->id, $branch->id);
        $this->assertEquals(50, $total);
    }

    public function test_aggregated_stock_excludes_archived_batches()
    {
        $product = Product::factory()->create();
        $branch = Branch::create(['name' => 'RHU 1']);

        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 30,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-002',
            'quantity' => 20,
            'expiry_date' => now()->subMonths(1),
            'is_archived' => true, // Archived
        ]);

        $total = LowStockSetting::getAggregatedStockForBranch($product->id, $branch->id);
        $this->assertEquals(30, $total);
    }

    public function test_is_low_stock_at_branch_with_multiple_batches()
    {
        $product = Product::factory()->create();
        $branch = Branch::create(['name' => 'RHU 1']);
        LowStockSetting::create(['threshold' => 20, 'is_global' => true]);

        // Total across batches: 5 + 8 = 13, below threshold of 20
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 5,
            'expiry_date' => now()->addMonths(3),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-002',
            'quantity' => 8,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);

        $this->assertTrue(LowStockSetting::isLowStockAtBranch($product->id, $branch->id));
    }

    public function test_is_not_low_stock_when_batches_sum_above_threshold()
    {
        $product = Product::factory()->create();
        $branch = Branch::create(['name' => 'RHU 1']);
        LowStockSetting::create(['threshold' => 20, 'is_global' => true]);

        // Total across batches: 15 + 12 = 27, above threshold of 20
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 15,
            'expiry_date' => now()->addMonths(3),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'BATCH-002',
            'quantity' => 12,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);

        $this->assertFalse(LowStockSetting::isLowStockAtBranch($product->id, $branch->id));
    }

    public function test_get_low_stock_products_across_branches()
    {
        $product = Product::factory()->create(['is_archived' => false]);
        $branch1 = Branch::create(['name' => 'RHU 1']);
        $branch2 = Branch::create(['name' => 'RHU 2']);
        LowStockSetting::create(['threshold' => 20, 'is_global' => true]);

        // Branch 1: 5 + 3 = 8 (low stock, below 20)
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch1->id,
            'batch_number' => 'B1-001',
            'quantity' => 5,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch1->id,
            'batch_number' => 'B1-002',
            'quantity' => 3,
            'expiry_date' => now()->addMonths(12),
            'is_archived' => false,
        ]);

        // Branch 2: 30 + 15 = 45 (not low stock, above 20)
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch2->id,
            'batch_number' => 'B2-001',
            'quantity' => 30,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch2->id,
            'batch_number' => 'B2-002',
            'quantity' => 15,
            'expiry_date' => now()->addMonths(12),
            'is_archived' => false,
        ]);

        $alerts = LowStockSetting::getLowStockProducts();

        // Only branch 1 should be in alerts
        $branch1Alerts = array_filter($alerts, fn($a) => $a['branch_id'] === $branch1->id);
        $branch2Alerts = array_filter($alerts, fn($a) => $a['branch_id'] === $branch2->id);

        $this->assertCount(1, $branch1Alerts);
        $this->assertCount(0, $branch2Alerts);

        $alert = array_values($branch1Alerts)[0];
        $this->assertEquals($product->id, $alert['product_id']);
        $this->assertEquals(8, $alert['total_stock']);
        $this->assertEquals(20, $alert['threshold']);
        $this->assertEquals(2, $alert['batch_count']);
    }

    public function test_get_low_stock_products_uses_branch_specific_threshold()
    {
        $product = Product::factory()->create(['is_archived' => false]);
        $branch1 = Branch::create(['name' => 'RHU 1']);
        $branch2 = Branch::create(['name' => 'RHU 2']);

        // Global threshold = 10
        LowStockSetting::create(['threshold' => 10, 'is_global' => true]);

        // Branch 2 specific override = 50
        LowStockSetting::create([
            'product_id' => $product->id,
            'branch_id' => $branch2->id,
            'threshold' => 50,
            'is_global' => false,
        ]);

        // Branch 1: stock = 15 (above global 10, NOT low stock)
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch1->id,
            'batch_number' => 'B1-001',
            'quantity' => 15,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);

        // Branch 2: stock = 30 (below branch-specific 50, IS low stock)
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch2->id,
            'batch_number' => 'B2-001',
            'quantity' => 30,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);

        $alerts = LowStockSetting::getLowStockProducts();

        $branch1Alerts = array_filter($alerts, fn($a) => $a['branch_id'] === $branch1->id);
        $branch2Alerts = array_filter($alerts, fn($a) => $a['branch_id'] === $branch2->id);

        $this->assertCount(0, $branch1Alerts);
        $this->assertCount(1, $branch2Alerts);

        $alert = array_values($branch2Alerts)[0];
        $this->assertEquals(30, $alert['total_stock']);
        $this->assertEquals(50, $alert['threshold']);
    }

    public function test_get_low_stock_products_skips_branches_with_no_inventory()
    {
        $product = Product::factory()->create(['is_archived' => false]);
        $branch1 = Branch::create(['name' => 'RHU 1']);
        $branch2 = Branch::create(['name' => 'RHU 2']); // No inventory here

        LowStockSetting::create(['threshold' => 10, 'is_global' => true]);

        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch1->id,
            'batch_number' => 'B1-001',
            'quantity' => 5,
            'expiry_date' => now()->addMonths(6),
            'is_archived' => false,
        ]);

        $alerts = LowStockSetting::getLowStockProducts();

        // Should only have alert for branch1 (has inventory, is low)
        // branch2 should be skipped (no inventory at all)
        $branch2Alerts = array_filter($alerts, fn($a) => $a['branch_id'] === $branch2->id);
        $this->assertCount(0, $branch2Alerts);
    }
}
