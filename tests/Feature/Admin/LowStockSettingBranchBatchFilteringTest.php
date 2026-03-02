<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LowStockSettingBranchBatchFilteringTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $level = UserLevel::firstOrCreate(['name' => 'admin']);
        $branch = Branch::factory()->create();

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_filter_options_endpoint_returns_branch_scoped_products_and_fefo_fifo_batches(): void
    {
        $user = $this->createUser();
        $branchA = Branch::factory()->create(['name' => 'RHU 1']);
        $branchB = Branch::factory()->create(['name' => 'RHU 2']);

        $productAlpha = Product::factory()->create(['generic_name' => 'Alpha', 'is_archived' => false]);
        $productBeta = Product::factory()->create(['generic_name' => 'Beta', 'is_archived' => false]);
        $productGamma = Product::factory()->create(['generic_name' => 'Gamma', 'is_archived' => false]);

        Inventory::create([
            'product_id' => $productBeta->id,
            'branch_id' => $branchA->id,
            'batch_number' => 'BETA-1',
            'quantity' => 8,
            'hold_qty' => 0,
            'expiry_date' => '2026-08-01',
            'is_archived' => false,
        ]);

        Inventory::create([
            'product_id' => $productGamma->id,
            'branch_id' => $branchB->id,
            'batch_number' => 'GAMMA-1',
            'quantity' => 8,
            'hold_qty' => 0,
            'expiry_date' => '2026-08-01',
            'is_archived' => false,
        ]);

        $batchA = Inventory::create([
            'product_id' => $productAlpha->id,
            'branch_id' => $branchA->id,
            'batch_number' => 'A',
            'quantity' => 15,
            'hold_qty' => 0,
            'expiry_date' => '2026-04-10',
            'is_archived' => false,
        ]);
        $batchA->created_at = Carbon::parse('2026-01-10 09:00:00');
        $batchA->save();

        $batchB = Inventory::create([
            'product_id' => $productAlpha->id,
            'branch_id' => $branchA->id,
            'batch_number' => 'B',
            'quantity' => 15,
            'hold_qty' => 0,
            'expiry_date' => '2026-04-10',
            'is_archived' => false,
        ]);
        $batchB->created_at = Carbon::parse('2026-01-05 09:00:00');
        $batchB->save();

        $batchC = Inventory::create([
            'product_id' => $productAlpha->id,
            'branch_id' => $branchA->id,
            'batch_number' => 'C',
            'quantity' => 15,
            'hold_qty' => 0,
            'expiry_date' => '2026-03-01',
            'is_archived' => false,
        ]);
        $batchC->created_at = Carbon::parse('2026-02-01 09:00:00');
        $batchC->save();

        Inventory::create([
            'product_id' => $productAlpha->id,
            'branch_id' => $branchA->id,
            'batch_number' => 'ZERO',
            'quantity' => 5,
            'hold_qty' => 5,
            'expiry_date' => '2026-02-15',
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.lowstock.filter-options', [
            'branch_id' => $branchA->id,
            'product_id' => $productAlpha->id,
        ]));

        $response->assertOk();

        $payload = $response->json();
        $productIds = collect($payload['products'] ?? [])->pluck('id')->all();

        $this->assertContains($productAlpha->id, $productIds);
        $this->assertContains($productBeta->id, $productIds);
        $this->assertNotContains($productGamma->id, $productIds);

        $batchNumbers = collect($payload['batches'] ?? [])->pluck('batch_number')->all();
        $this->assertSame(['C', 'B', 'A'], $batchNumbers);
        $this->assertNotContains('ZERO', $batchNumbers);
    }

    public function test_analytics_low_stock_alerts_report_branch_override_before_global_override(): void
    {
        $user = $this->createUser();
        $branch1 = Branch::factory()->create(['name' => 'RHU 1']);
        $branch2 = Branch::factory()->create(['name' => 'RHU 2']);
        $product = Product::factory()->create(['generic_name' => 'Paracetamol', 'is_archived' => false]);

        LowStockSetting::create([
            'is_global' => true,
            'threshold' => 5,
            'product_id' => null,
            'branch_id' => null,
        ]);
        LowStockSetting::create([
            'is_global' => false,
            'product_id' => $product->id,
            'branch_id' => null,
            'threshold' => 9,
        ]);
        LowStockSetting::create([
            'is_global' => false,
            'product_id' => $product->id,
            'branch_id' => $branch2->id,
            'threshold' => 12,
        ]);

        $branch1Batch = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch1->id,
            'batch_number' => 'B1-ALERT',
            'quantity' => 7,
            'hold_qty' => 0,
            'expiry_date' => '2026-10-01',
            'is_archived' => false,
        ]);

        $branch2Batch = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch2->id,
            'batch_number' => 'B2-ALERT',
            'quantity' => 7,
            'hold_qty' => 0,
            'expiry_date' => '2026-10-01',
            'is_archived' => false,
        ]);

        $branch1Response = $this->actingAs($user)
            ->getJson(route('admin.analytics.low-stock', ['branch_id' => $branch1->id]));
        $branch1Response->assertOk();
        $branch1Alerts = collect($branch1Response->json('alerts'));
        $branch1Alert = $branch1Alerts->firstWhere('inventory_id', $branch1Batch->id);

        $this->assertNotNull($branch1Alert);
        $this->assertSame(9, $branch1Alert['threshold']);
        $this->assertSame('global_override', $branch1Alert['threshold_source']);

        $branch2Response = $this->actingAs($user)
            ->getJson(route('admin.analytics.low-stock', ['branch_id' => $branch2->id]));
        $branch2Response->assertOk();
        $branch2Alerts = collect($branch2Response->json('alerts'));
        $branch2Alert = $branch2Alerts->firstWhere('inventory_id', $branch2Batch->id);

        $this->assertNotNull($branch2Alert);
        $this->assertSame(12, $branch2Alert['threshold']);
        $this->assertSame('branch_override', $branch2Alert['threshold_source']);
    }

    public function test_low_stock_index_filters_by_selected_branch_product_and_batch(): void
    {
        $user = $this->createUser();
        $branch = Branch::factory()->create(['name' => 'RHU 1']);
        $product = Product::factory()->create(['generic_name' => 'Amoxicillin', 'is_archived' => false]);

        LowStockSetting::create([
            'is_global' => true,
            'threshold' => 4,
            'product_id' => null,
            'branch_id' => null,
        ]);
        LowStockSetting::create([
            'is_global' => false,
            'product_id' => $product->id,
            'branch_id' => null,
            'threshold' => 10,
        ]);

        $batchOne = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'AMX-ONE',
            'quantity' => 3,
            'hold_qty' => 0,
            'expiry_date' => '2026-07-01',
            'is_archived' => false,
        ]);
        $batchTwo = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => 'AMX-TWO',
            'quantity' => 2,
            'hold_qty' => 0,
            'expiry_date' => '2026-08-01',
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.lowstock.index', [
            'alert_branch_id' => $branch->id,
            'alert_product_id' => $product->id,
            'alert_batch_id' => $batchOne->id,
        ]));

        $response->assertOk();
        $response->assertSee('AMX-ONE');
        $response->assertSee('focus_inventory_id='.$batchOne->id);
        $response->assertDontSee('focus_inventory_id='.$batchTwo->id);
        $response->assertSee('Global Override');
    }
}
