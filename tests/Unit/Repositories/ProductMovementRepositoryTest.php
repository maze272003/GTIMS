<?php

namespace Tests\Unit\Repositories;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use App\Repositories\Eloquent\ProductMovementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMovementRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductMovementRepository $repository;
    private Branch $primaryBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductMovementRepository(new ProductMovement());
        $this->primaryBranch = Branch::factory()->create([
            'name' => 'RHU Primary',
            'code' => 'rhu-primary',
        ]);
    }

    public function test_build_filtered_query_prioritizes_exact_batch_matches(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice Encoder',
            'branch_id' => $this->primaryBranch->id,
        ]);

        $exactProduct = Product::factory()->create([
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
        ]);

        $fuzzyProduct = Product::factory()->create([
            'generic_name' => 'Batch-777 Relief',
            'brand_name' => 'Relief Plus',
        ]);

        $exactInventory = $this->createInventory($exactProduct, $this->primaryBranch, 'BATCH-777');
        $fuzzyInventory = $this->createInventory($fuzzyProduct, $this->primaryBranch, 'OTHER-100');

        $exactMovement = $this->createMovement($exactProduct, $exactInventory, $user, 'New delivery received');
        $fuzzyMovement = $this->createMovement($fuzzyProduct, $fuzzyInventory, $user, 'Notes mention batch-777 only');

        $results = $this->repository
            ->buildFilteredQuery(['search' => 'BATCH-777'])
            ->get();

        $this->assertSame(
            [$exactMovement->id, $fuzzyMovement->id],
            $results->pluck('id')->all()
        );
    }

    public function test_get_today_stats_returns_branch_scoped_aggregates(): void
    {
        $secondaryBranch = Branch::factory()->create([
            'name' => 'RHU Secondary',
            'code' => 'rhu-secondary',
        ]);

        $user = User::factory()->create(['branch_id' => $this->primaryBranch->id]);
        $product = Product::factory()->create();
        $primaryInventory = $this->createInventory($product, $this->primaryBranch, 'TODAY-001');
        $secondaryInventory = $this->createInventory($product, $secondaryBranch, 'TODAY-002');

        $this->createMovement($product, $primaryInventory, $user, 'Inbound shipment', 'IN', 12, now());
        $this->createMovement($product, $primaryInventory, $user, 'Dispensed stock', 'OUT', 4, now());
        $this->createMovement($product, $primaryInventory, $user, 'Yesterday movement', 'IN', 99, now()->subDay());
        $this->createMovement($product, $secondaryInventory, $user, 'Other branch movement', 'IN', 7, now());

        $stats = $this->repository->getTodayStats($this->primaryBranch->id);

        $this->assertSame(2, $stats['movementsTodayCount']);
        $this->assertSame(12, $stats['itemsInToday']);
        $this->assertSame(4, $stats['itemsOutToday']);
    }

    private function createInventory(Product $product, Branch $branch, string $batchNumber): Inventory
    {
        return Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'batch_number' => $batchNumber,
            'quantity' => 100,
            'expiry_date' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);
    }

    private function createMovement(
        Product $product,
        Inventory $inventory,
        User $user,
        string $description,
        string $type = 'IN',
        int $quantity = 10,
        $createdAt = null
    ): ProductMovement {
        $createdAt ??= now();
        $before = $type === 'IN' ? 90 : 100;
        $after = $type === 'IN' ? $before + $quantity : $before - $quantity;

        $movement = ProductMovement::create([
            'product_id' => $product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $user->id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'description' => $description,
        ]);

        $movement->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $movement->fresh();
    }
}
