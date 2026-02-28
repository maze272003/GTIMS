<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\IncomingRequest;
use App\Models\Product;
use App\Models\RequestItem;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomingRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $level = UserLevel::create(['name' => 'Admin']);
        $this->branch = Branch::factory()->create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_create_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.requests.create'));

        $response->assertOk();
        $response->assertSee('Create Request');
    }

    public function test_store_accepts_form_quantity_field_and_creates_record(): void
    {
        $product = Product::factory()->create([
            'is_archived' => false,
        ]);

        $payload = [
            'branch_id' => $this->branch->id,
            'department' => 'Pharmacy',
            'priority' => 'high',
            'remarks' => 'Urgent stock request',
            'items' => [
                [
                    'product_id' => $product->id,
                    // Matches the original form field that previously failed server validation.
                    'quantity' => 5,
                    'allow_substitution' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('admin.requests.store'), $payload);

        $response->assertRedirect(route('admin.requests.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('incoming_requests', [
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'department' => 'Pharmacy',
            'priority' => 'high',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('request_items', [
            'product_id' => $product->id,
            'quantity_requested' => 5,
            'allow_substitution' => 1,
        ]);
    }

    public function test_show_page_renders_for_existing_request(): void
    {
        $product = Product::factory()->create([
            'is_archived' => false,
        ]);

        $incomingRequest = IncomingRequest::create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'department' => 'Pharmacy',
            'priority' => 'normal',
            'status' => 'draft',
            'remarks' => 'Show page regression test',
        ]);

        RequestItem::create([
            'incoming_request_id' => $incomingRequest->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'allow_substitution' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.requests.show', $incomingRequest->id));

        $response->assertOk();
        $response->assertSee("Request #{$incomingRequest->id}");
        $response->assertSee('Requested Items');
        $response->assertSee((string) $product->generic_name);
    }
}
