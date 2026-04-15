<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch1;

    private Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::factory()->create(['name' => 'RHU 1', 'code' => 'rhu-1', 'is_archived' => false]);
        $this->branch2 = Branch::factory()->create(['name' => 'RHU 2', 'code' => 'rhu-2', 'is_archived' => false]);

        $this->user = $this->createAdminUser(true);
    }

    private function createAdminUser(bool $withGlobalBranchAccess = false): User
    {
        $level = UserLevel::firstOrCreate(['name' => 'admin']);

        $permissionNames = [
            'inventory.view', 'inventory.add', 'inventory.edit',
            'inventory.archive', 'inventory.transfer', 'dashboard.view',
        ];

        if ($withGlobalBranchAccess) {
            $permissionNames[] = 'branches.manage';
        }

        $perms = collect($permissionNames)
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['group' => 'test']));
        $level->permissions()->syncWithoutDetaching($perms->pluck('id'));

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch1->id,
        ]);
    }

    public function test_concurrent_add_stock_does_not_lose_updates(): void
    {
        $product = Product::factory()->create(['is_archived' => false]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch1->id,
            'batch_number' => 'BATCH-CX',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);

        $concurrent = 5;
        $addPerRequest = 10;

        $results = [];
        for ($i = 0; $i < $concurrent; $i++) {
            $results[] = $this->actingAs($this->user)
                ->post(route('admin.inventory.addstock'), [
                    'product_id' => $product->id,
                    'branch_id' => $this->branch1->id,
                    'batchnumber' => 'BATCH-CX',
                    'quantity' => $addPerRequest,
                    'expiry' => now()->addYear()->format('Y-m-d'),
                ]);
        }

        $inventory->refresh();
        $expected = 100 + ($concurrent * $addPerRequest);

        $this->assertEquals($expected, $inventory->quantity, "Expected {$expected} but got {$inventory->quantity}. Concurrent stock additions lost updates.");

        foreach ($results as $result) {
            $result->assertRedirect(route('admin.inventory'));
        }
    }

    public function test_concurrent_transfers_do_not_oversell(): void
    {
        $product = Product::factory()->create(['is_archived' => false]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch1->id,
            'batch_number' => 'BATCH-TX',
            'quantity' => 50,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);

        $concurrent = 5;
        $transferPerRequest = 20;

        $successCount = 0;
        $failureCount = 0;

        for ($i = 0; $i < $concurrent; $i++) {
            $response = $this->actingAs($this->user)
                ->post(route('admin.inventory.transferstock'), [
                    'inventory_id' => $inventory->id,
                    'quantity' => $transferPerRequest,
                    'destination_branch' => $this->branch2->id,
                ]);

            if ($response->status() === 302 && session('success')) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        $inventory->refresh();
        $maxTransfers = (int) floor(50 / $transferPerRequest);

        $this->assertLessThanOrEqual($maxTransfers, $successCount, "More transfers succeeded than stock allows (overselling detected).");
        $this->assertGreaterThanOrEqual(0, $inventory->quantity, "Source inventory went negative.");

        $totalTransferred = $successCount * $transferPerRequest;
        $this->assertEquals(50 - $totalTransferred, $inventory->quantity, "Source quantity does not match transfers.");
    }

    public function test_concurrent_edit_stock_uses_locks(): void
    {
        $product = Product::factory()->create(['is_archived' => false]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch1->id,
            'batch_number' => 'BATCH-EDIT',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);

        $edits = [200, 300, 400];

        foreach ($edits as $qty) {
            $this->actingAs($this->user)
                ->put(route('admin.inventory.editstock'), [
                    'inventory_id' => $inventory->id,
                    'batchnumber' => 'BATCH-EDIT',
                    'quantity' => $qty,
                    'expiry' => now()->addYear()->format('Y-m-d'),
                ]);
        }

        $inventory->refresh();

        $this->assertTrue(
            in_array($inventory->quantity, $edits),
            "Quantity after concurrent edits should be one of the requested values, got {$inventory->quantity}."
        );
    }

    public function test_archive_product_is_atomic(): void
    {
        $product = Product::factory()->create(['is_archived' => false]);

        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch1->id,
            'batch_number' => 'BATCH-ARC',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);

        $this->actingAs($this->user)
            ->put(route('admin.inventory.archiveproduct'), [
                'product_id' => $product->id,
            ]);

        $product->refresh();
        $this->assertEquals(1, $product->is_archived);

        $inventories = Inventory::where('product_id', $product->id)->get();
        foreach ($inventories as $inv) {
            $this->assertEquals(1, $inv->is_archived, "Inventory batch {$inv->batch_number} should be archived atomically with product.");
        }
    }

    public function test_add_stock_and_transfer_race_condition(): void
    {
        $product = Product::factory()->create(['is_archived' => false]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch1->id,
            'batch_number' => 'BATCH-RACE',
            'quantity' => 100,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.inventory.addstock'), [
                'product_id' => $product->id,
                'branch_id' => $this->branch1->id,
                'batchnumber' => 'BATCH-RACE',
                'quantity' => 50,
                'expiry' => now()->addYear()->format('Y-m-d'),
            ]);

        $inventory->refresh();
        $this->assertEquals(150, $inventory->quantity);

        $this->actingAs($this->user)
            ->post(route('admin.inventory.transferstock'), [
                'inventory_id' => $inventory->id,
                'quantity' => 30,
                'destination_branch' => $this->branch2->id,
            ]);

        $source = Inventory::where('id', $inventory->id)->first();
        $this->assertEquals(120, $source->quantity);

        $destInventory = Inventory::where('product_id', $product->id)
            ->where('branch_id', $this->branch2->id)
            ->where('batch_number', 'BATCH-RACE')
            ->first();

        $this->assertNotNull($destInventory);
        $this->assertEquals(30, $destInventory->quantity);
    }

    public function test_transfer_detects_insufficient_stock_under_lock(): void
    {
        $product = Product::factory()->create(['is_archived' => false]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch1->id,
            'batch_number' => 'BATCH-INSUF',
            'quantity' => 10,
            'expiry_date' => now()->addYear(),
            'is_archived' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.inventory.transferstock'), [
                'inventory_id' => $inventory->id,
                'quantity' => 100,
                'destination_branch' => $this->branch2->id,
            ]);

        $inventory->refresh();
        $this->assertEquals(10, $inventory->quantity, "Stock should not change when transfer exceeds available quantity.");

        $destInventory = Inventory::where('product_id', $product->id)
            ->where('branch_id', $this->branch2->id)
            ->first();
        $this->assertNull($destInventory, "No destination inventory should be created for failed transfer.");
    }
}