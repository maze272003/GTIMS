<?php

namespace Tests\Unit\Repositories;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Repositories\Eloquent\InventoryAdminRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdminRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private InventoryAdminRepository $repository;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InventoryAdminRepository();
        $this->branch = Branch::factory()->create([
            'name' => 'RHU North',
            'code' => 'rhu-north',
        ]);
    }

    public function test_build_active_inventory_by_branch_query_prioritizes_exact_batch_matches(): void
    {
        $exactProduct = Product::factory()->create([
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Biogesic',
        ]);

        $fuzzyProduct = Product::factory()->create([
            'generic_name' => 'Batch-555 Capsules',
            'brand_name' => 'Generic Relief',
        ]);

        $exactInventory = $this->createInventory($exactProduct, 'BATCH-555', 80);
        $fuzzyInventory = $this->createInventory($fuzzyProduct, 'OTHER-111', 80);

        $results = $this->repository
            ->buildActiveInventoryByBranchQuery($this->branch->id, 'BATCH-555')
            ->get();

        $this->assertSame(
            [$exactInventory->id, $fuzzyInventory->id],
            $results->pluck('id')->all()
        );
    }

    public function test_get_inventory_overview_stats_returns_bucket_counts(): void
    {
        $this->createInventory(Product::factory()->create(), 'IN-STOCK', 150, now()->addMonths(6));
        $this->createInventory(Product::factory()->create(), 'LOW-STOCK', 25, now()->addMonths(3));
        $this->createInventory(Product::factory()->create(), 'EXPIRED', 10, now()->subDay());
        $this->createInventory(Product::factory()->create(), 'NEARLY-EXPIRING', 5, now()->addDays(10));
        $this->createInventory(Product::factory()->create(), 'OUT-OF-STOCK', 0, now()->addMonths(2));

        $stats = $this->repository->getInventoryOverviewStats([$this->branch->id]);

        $this->assertSame(1, $stats['in_stock']);
        $this->assertSame(3, $stats['low_stock']);
        $this->assertSame(1, $stats['expired']);
        $this->assertSame(1, $stats['nearly_expired']);
    }

    private function createInventory(Product $product, string $batchNumber, int $quantity, $expiryDate = null): Inventory
    {
        return Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => $batchNumber,
            'quantity' => $quantity,
            'expiry_date' => ($expiryDate ?? now()->addYear())->toDateString(),
            'is_archived' => false,
        ]);
    }
}
