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
use App\Models\HoldStatusHistory;
use App\Models\AuditEvent;
use App\Services\HoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HoldServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HoldService $service;
    protected $branch;
    protected $user;
    protected $product;
    protected $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HoldService();
        
        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
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
            'expiry_date' => now()->addYear(),
        ]);
    }

    public function test_create_hold_creates_hold_with_items_and_history()
    {
        $hold = $this->service->createHold(
            [
                'branch_id' => $this->branch->id,
                'type' => 'quarantine',
                'reason_code' => 'damaged',
                'remarks' => 'Test hold',
            ],
            [
                ['product_id' => $this->product->id, 'inventory_id' => $this->inventory->id, 'quantity' => 10],
            ],
            $this->user->id
        );

        $this->assertDatabaseHas('holds', ['id' => $hold->id, 'status' => 'pending']);
        $this->assertDatabaseHas('hold_items', ['hold_id' => $hold->id, 'quantity' => 10]);
        $this->assertDatabaseHas('hold_status_history', ['hold_id' => $hold->id, 'new_status' => 'pending']);
        $this->assertDatabaseHas('audit_events', ['action' => 'hold.created', 'entity_id' => $hold->id]);
    }

    public function test_approve_hold_changes_status_and_records_history()
    {
        $hold = Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'reservation',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);

        $approver = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $this->user->user_level_id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->service->approveHold($hold, $approver->id, 'Looks good');

        $this->assertEquals('approved', $result->status);
        $this->assertEquals($approver->id, $result->approved_by);
        $this->assertDatabaseHas('hold_status_history', [
            'hold_id' => $hold->id,
            'old_status' => 'pending',
            'new_status' => 'approved',
        ]);
    }

    public function test_release_hold_changes_status()
    {
        $hold = Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'quarantine',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);

        $result = $this->service->releaseHold($hold, $this->user->id, 'Issue resolved');
        $this->assertEquals('released', $result->status);
    }

    public function test_expire_holds_marks_expired_holds()
    {
        Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'reservation',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'approved',
            'expires_at' => now()->subHour(),
        ]);

        $count = $this->service->expireHolds();

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('holds', ['status' => 'expired']);
    }

    public function test_expire_holds_ignores_non_expired_holds()
    {
        Hold::create([
            'branch_id' => $this->branch->id,
            'type' => 'reservation',
            'reason_code' => 'test',
            'created_by' => $this->user->id,
            'status' => 'approved',
            'expires_at' => now()->addDay(),
        ]);

        $count = $this->service->expireHolds();
        $this->assertEquals(0, $count);
    }
}
