<?php

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\Hold;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use App\Services\HoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HoldServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HoldService $service;
    protected Branch $branch;
    protected User $user;
    protected Product $product;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HoldService::class);

        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::factory()->create();
        $this->inventory = Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
            'onhand_qty' => 100,
            'hold_qty' => 0,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);
    }

    public function test_holding_within_available_reserves_hold_qty_only(): void
    {
        $hold = $this->service->createHold(
            [
                'branch_id' => $this->branch->id,
                'type' => 'reservation',
                'reason_code' => 'PROGRAM_ALLOCATION',
                'remarks' => 'Reserve for scheduled use',
            ],
            [
                ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 30],
            ],
            $this->user->id
        );

        $this->inventory->refresh();

        $this->assertSame('pending', $hold->status);
        $this->assertSame(100, (int) $this->inventory->onhand_qty);
        $this->assertSame(30, (int) $this->inventory->hold_qty);
        $this->assertSame(70, (int) $this->inventory->available_quantity);
        $this->assertDatabaseHas('hold_items', ['hold_id' => $hold->id, 'inventory_id' => $this->inventory->id, 'quantity' => 30]);
        $this->assertDatabaseHas('audit_events', ['action' => 'hold.created', 'entity_id' => $hold->id]);
    }

    public function test_holding_beyond_available_must_fail(): void
    {
        $this->inventory->update([
            'hold_qty' => 90,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->service->createHold(
                [
                    'branch_id' => $this->branch->id,
                    'type' => 'quarantine',
                    'reason_code' => 'QUALITY_CHECK',
                ],
                [
                    ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 20],
                ],
                $this->user->id
            );
        } finally {
            $this->inventory->refresh();
            $this->assertSame(100, (int) $this->inventory->onhand_qty);
            $this->assertSame(90, (int) $this->inventory->hold_qty);
            $this->assertDatabaseCount('holds', 0);
        }
    }

    public function test_releasing_hold_decreases_hold_qty_and_keeps_onhand_qty(): void
    {
        $hold = $this->service->createHold(
            [
                'branch_id' => $this->branch->id,
                'type' => 'reservation',
                'reason_code' => 'EVENT',
            ],
            [
                ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 40],
            ],
            $this->user->id
        );

        $this->service->approveHold($hold, $this->user->id, 'Approved');
        $released = $this->service->releaseHold($hold, $this->user->id, 'Release after event');

        $this->inventory->refresh();
        $this->assertSame('released', $released->status);
        $this->assertSame(100, (int) $this->inventory->onhand_qty);
        $this->assertSame(0, (int) $this->inventory->hold_qty);
    }

    public function test_cancelling_hold_decreases_hold_qty_and_keeps_onhand_qty(): void
    {
        $hold = $this->service->createHold(
            [
                'branch_id' => $this->branch->id,
                'type' => 'quarantine',
                'reason_code' => 'STOCK_REVIEW',
            ],
            [
                ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 25],
            ],
            $this->user->id
        );

        $cancelled = $this->service->cancelHold($hold, $this->user->id, 'Cancelled by requester');

        $this->inventory->refresh();
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(100, (int) $this->inventory->onhand_qty);
        $this->assertSame(0, (int) $this->inventory->hold_qty);
    }

    public function test_concurrent_holds_on_same_item_prevent_oversubscription(): void
    {
        $firstHold = $this->service->createHold(
            [
                'branch_id' => $this->branch->id,
                'type' => 'reservation',
                'reason_code' => 'ALLOCATION_A',
            ],
            [
                ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 70],
            ],
            $this->user->id
        );

        $this->assertInstanceOf(Hold::class, $firstHold);

        $this->expectException(ValidationException::class);

        try {
            // Simulates a competing hold attempt after the first transaction reserved stock.
            $this->service->createHold(
                [
                    'branch_id' => $this->branch->id,
                    'type' => 'reservation',
                    'reason_code' => 'ALLOCATION_B',
                ],
                [
                    ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 40],
                ],
                $this->user->id
            );
        } finally {
            $this->inventory->refresh();
            $this->assertSame(70, (int) $this->inventory->hold_qty);
            $this->assertSame(30, (int) $this->inventory->available_quantity);
            $this->assertDatabaseCount('holds', 1);
        }
    }

    public function test_pull_out_blocks_held_stock_without_override(): void
    {
        $this->inventory->update([
            'hold_qty' => 40,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->pullOutInventory(
            inventoryId: $this->inventory->id,
            quantity: 70,
            userId: $this->user->id,
            reason: 'Damaged stocks',
            referenceNo: 'PO-001',
            overrideHeld: false
        );
    }
}
