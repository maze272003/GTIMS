<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\IncomingRequest;
use App\Models\RequestItem;
use App\Models\RequestStatusHistory;
use App\Models\IdempotencyKey;
use App\Services\RequestWorkflowService;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RequestWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RequestWorkflowService $service;
    protected $branch;
    protected $user;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RequestWorkflowService(new AvailabilityService());
        
        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::factory()->create();
    }

    public function test_create_request_creates_draft_with_items()
    {
        $request = $this->service->createRequest(
            ['branch_id' => $this->branch->id, 'department' => 'Pharmacy', 'priority' => 'high'],
            [['product_id' => $this->product->id, 'quantity_requested' => 50]],
            $this->user->id
        );

        $this->assertEquals('draft', $request->status);
        $this->assertDatabaseHas('incoming_requests', ['id' => $request->id, 'status' => 'draft']);
        $this->assertDatabaseHas('request_items', ['incoming_request_id' => $request->id, 'quantity_requested' => 50]);
        $this->assertDatabaseHas('request_status_history', ['incoming_request_id' => $request->id, 'new_status' => 'draft']);
    }

    public function test_transition_status_follows_state_machine()
    {
        $request = $this->service->createRequest(
            ['branch_id' => $this->branch->id, 'priority' => 'normal'],
            [['product_id' => $this->product->id, 'quantity_requested' => 10]],
            $this->user->id
        );

        // draft -> requested
        $request = $this->service->transitionStatus($request, 'requested', $this->user->id);
        $this->assertEquals('requested', $request->status);

        // requested -> review
        $request = $this->service->transitionStatus($request, 'review', $this->user->id);
        $this->assertEquals('review', $request->status);

        // review -> approved
        $request = $this->service->transitionStatus($request, 'approved', $this->user->id);
        $this->assertEquals('approved', $request->status);
    }

    public function test_invalid_transition_throws_exception()
    {
        $request = $this->service->createRequest(
            ['branch_id' => $this->branch->id, 'priority' => 'normal'],
            [['product_id' => $this->product->id, 'quantity_requested' => 10]],
            $this->user->id
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->transitionStatus($request, 'approved', $this->user->id); // Can't go from draft to approved
    }

    public function test_idempotent_transition()
    {
        $request = $this->service->createRequest(
            ['branch_id' => $this->branch->id, 'priority' => 'normal'],
            [['product_id' => $this->product->id, 'quantity_requested' => 10]],
            $this->user->id
        );

        $key = 'unique-key-123';
        $request = $this->service->transitionStatus($request, 'requested', $this->user->id, null, $key);
        $this->assertEquals('requested', $request->status);

        // Second call with same key should be idempotent
        $request2 = $this->service->transitionStatus($request, 'requested', $this->user->id, null, $key);
        $this->assertEquals('requested', $request2->status);
    }

    public function test_fulfill_request_deducts_stock()
    {
        Inventory::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
        ]);

        $request = $this->service->createRequest(
            ['branch_id' => $this->branch->id, 'priority' => 'normal'],
            [['product_id' => $this->product->id, 'quantity_requested' => 20]],
            $this->user->id
        );

        $request = $this->service->transitionStatus($request, 'requested', $this->user->id);
        $request = $this->service->transitionStatus($request, 'review', $this->user->id);
        $request = $this->service->transitionStatus($request, 'approved', $this->user->id);

        $request = $this->service->fulfillRequest($request, $this->user->id);

        $this->assertContains($request->status, ['fulfilling', 'fulfilled']);
        $this->assertDatabaseHas('inventories', ['batch_number' => 'B1', 'quantity' => 80]);
    }
}
