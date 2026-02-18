<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Hold;
use App\Models\HoldItem;
use App\Models\ProductMovement;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AvailabilityService $service;
    protected $branch;
    protected $user;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService();
        
        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::factory()->create();
    }

    public function test_get_on_hand_returns_total_quantity()
    {
        Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity' => 50,
            'expiry_date' => now()->addYear(),
        ]);
        Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B2',
            'quantity' => 30,
            'expiry_date' => now()->addYear(),
        ]);

        $this->assertEquals(80, $this->service->getOnHand($this->product->id, $this->branch->id));
    }

    public function test_get_held_quantity_returns_sum_of_active_holds()
    {
        $inv = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
        ]);

        $hold = Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'reservation',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);

        HoldItem::create([
            'hold_id' => $hold->id,
            'product_id' => $this->product->id,
            'inventory_id' => $inv->id,
            'quantity' => 20,
        ]);

        $this->assertEquals(20, $this->service->getHeldQuantity($this->product->id, $this->branch->id));
    }

    public function test_get_available_returns_on_hand_minus_held()
    {
        $inv = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
        ]);

        $hold = Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'quarantine',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);

        HoldItem::create([
            'hold_id' => $hold->id,
            'product_id' => $this->product->id,
            'inventory_id' => $inv->id,
            'quantity' => 30,
        ]);

        $this->assertEquals(70, $this->service->getAvailable($this->product->id, $this->branch->id));
    }

    public function test_allocate_fefo_returns_earliest_expiry_first()
    {
        Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'LATER',
            'quantity' => 50,
            'expiry_date' => now()->addYears(2),
        ]);
        $earlyBatch = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'EARLIER',
            'quantity' => 30,
            'expiry_date' => now()->addMonths(3),
        ]);

        $allocations = $this->service->allocateFEFO($this->product->id, 20, $this->branch->id);

        $this->assertCount(1, $allocations);
        $this->assertEquals($earlyBatch->id, $allocations[0]['inventory_id']);
        $this->assertEquals(20, $allocations[0]['quantity']);
    }

    public function test_allocate_fefo_skips_held_quantities()
    {
        $inv = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'HELD-BATCH',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(3),
        ]);

        $hold = Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'reservation',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);

        HoldItem::create([
            'hold_id' => $hold->id,
            'product_id' => $this->product->id,
            'inventory_id' => $inv->id,
            'quantity' => 50,
        ]);

        $laterBatch = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'FREE-BATCH',
            'quantity' => 40,
            'expiry_date' => now()->addYear(),
        ]);

        $allocations = $this->service->allocateFEFO($this->product->id, 30, $this->branch->id);

        $this->assertCount(1, $allocations);
        $this->assertEquals($laterBatch->id, $allocations[0]['inventory_id']);
        $this->assertEquals(30, $allocations[0]['quantity']);
    }

    public function test_deduct_stock_creates_movements()
    {
        $inv = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
        ]);

        $allocations = [['inventory_id' => $inv->id, 'quantity' => 25]];
        $this->service->deductStock($allocations, $this->product->id, $this->user->id, 'Test deduction');

        $this->assertDatabaseHas('inventories', ['id' => $inv->id, 'quantity' => 75]);
        $this->assertDatabaseHas('product_movements', [
            'inventory_id' => $inv->id,
            'type' => 'OUT',
            'quantity' => 25,
            'quantity_before' => 100,
            'quantity_after' => 75,
        ]);
    }
}
