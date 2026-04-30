<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderSourceBatchFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderUser(bool $withOrdersViewPermission = true, bool $withGlobalBranchAccess = false): User
    {
        $level = UserLevel::firstOrCreate(['name' => 'admin']);
        $requestingBranch = Branch::factory()->create();

        if ($withOrdersViewPermission) {
            $permissionIds = collect(['orders.view', 'orders.create'])
                ->when($withGlobalBranchAccess, fn ($permissions) => $permissions->push('branches.manage'))
                ->map(fn (string $name) => Permission::firstOrCreate([
                    'name' => $name,
                ], [
                    'group' => 'orders',
                ])->id)
                ->all();

            $level->permissions()->syncWithoutDetaching($permissionIds);
        }

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $requestingBranch->id,
        ]);
    }

    public function test_order_submission_creates_pending_order_without_touching_inventory(): void
    {
        $user = $this->createOrderUser(true, true);
        $sourceBranch = Branch::factory()->create();
        $product = Product::factory()->create(['is_archived' => 0]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'BATCH-123',
            'quantity' => 20,
            'hold_qty' => 0,
            'expiry_date' => '2026-04-10',
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->post(route('admin.orders.store'), [
            'remarks' => 'Urgent replenishment',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.orders.index'));
        $response->assertSessionHas('success');

        $order = Order::query()->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 5,
            'source_branch_id' => null,
            'source_inventory_id' => null,
            'source_batch_number' => null,
        ]);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'onhand_qty' => 20,
            'quantity' => 20,
        ]);

        $this->assertDatabaseCount('product_movements', 0);
    }

    public function test_order_submission_requires_only_product_and_quantity(): void
    {
        $user = $this->createOrderUser(true, true);
        $product = Product::factory()->create(['is_archived' => 0]);

        $response = $this->from(route('admin.orders.create'))
            ->actingAs($user)
            ->post(route('admin.orders.store'), [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.orders.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('product_movements', 0);
    }

    public function test_source_inventory_endpoint_returns_fefo_fifo_sorted_non_zero_batches(): void
    {
        $user = $this->createOrderUser(true, true);
        $sourceBranch = Branch::factory()->create();
        $product = Product::factory()->create(['is_archived' => 0]);

        $batchA = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'A',
            'quantity' => 15,
            'hold_qty' => 0,
            'expiry_date' => '2026-04-10',
            'is_archived' => false,
        ]);
        $batchA->created_at = Carbon::parse('2026-01-10 09:00:00');
        $batchA->save();

        $batchB = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'B',
            'quantity' => 15,
            'hold_qty' => 0,
            'expiry_date' => '2026-04-10',
            'is_archived' => false,
        ]);
        $batchB->created_at = Carbon::parse('2026-01-05 09:00:00');
        $batchB->save();

        $batchC = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'C',
            'quantity' => 15,
            'hold_qty' => 0,
            'expiry_date' => '2026-03-01',
            'is_archived' => false,
        ]);
        $batchC->created_at = Carbon::parse('2026-02-01 09:00:00');
        $batchC->save();

        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'ZERO',
            'quantity' => 5,
            'hold_qty' => 5,
            'expiry_date' => '2026-02-15',
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.orders.source-inventory', ['branch_id' => $sourceBranch->id]));

        $response->assertOk();

        $payload = $response->json();
        $items = $payload['inventory_by_product'][$product->id] ?? [];
        $batchNumbers = array_map(fn ($item) => $item['batch_number'], $items);

        $this->assertSame(['C', 'B', 'A'], $batchNumbers);
        $this->assertNotContains('ZERO', $batchNumbers);
    }
}
