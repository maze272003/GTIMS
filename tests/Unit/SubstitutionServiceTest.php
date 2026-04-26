<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSubstitute;
use App\Services\AvailabilityService;
use App\Services\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

class SubstitutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private SubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $this->service = new SubstitutionService(new AvailabilityService);
    }

    public function test_handles_missing_product_gracefully(): void
    {
        $result = $this->service->suggestSubstitutes(999999, $this->branch->id);

        $this->assertSame([], $result);
    }

    public function test_handles_null_inventory_quantities(): void
    {
        $original = $this->createProduct('Paracetamol', 'Tablet', '500mg');
        $equivalent = $this->createProduct('Paracetamol', 'Tablet', '500mg');

        $inventory = Inventory::create([
            'product_id' => $equivalent->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'NULL-001',
            'quantity' => 0,
            'onhand_qty' => null,
            'hold_qty' => null,
            'expiry_date' => now()->addMonth()->toDateString(),
            'is_archived' => false,
        ]);

        $suggestions = $this->service->suggestSubstitutes($original->id, $this->branch->id);

        $this->assertSame([], $suggestions);
    }

    public function test_handles_corrupted_pivot_data(): void
    {
        Log::spy();

        $original = $this->createProduct('Cetirizine', 'Tablet', '10mg');
        $substitute = $this->createProduct('Cetirizine', 'Tablet', '10mg');

        ProductSubstitute::create([
            'product_id' => $original->id,
            'substitute_product_id' => $substitute->id,
            'priority' => 1,
        ]);

        DB::table('product_substitutes')
            ->where('product_id', $original->id)
            ->where('substitute_product_id', $substitute->id)
            ->update(['priority' => 'bad-priority']);

        Inventory::create([
            'product_id' => $substitute->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'PIVOT-001',
            'quantity' => 25,
            'onhand_qty' => 25,
            'hold_qty' => 0,
            'expiry_date' => now()->addMonth()->toDateString(),
            'is_archived' => false,
        ]);

        $suggestions = $this->service->suggestSubstitutes($original->id, $this->branch->id);

        $this->assertCount(1, $suggestions);
        $this->assertSame(0, $suggestions[0]['priority']);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message): bool {
            return $message === 'substitutions.invalid_priority_value';
        })->once();
    }

    public function test_prevents_duplicate_suggestions(): void
    {
        $original = $this->createProduct('Loratadine', 'Tablet', '10mg');
        $substitute = $this->createProduct('Loratadine', 'Tablet', '10mg');

        ProductSubstitute::create([
            'product_id' => $original->id,
            'substitute_product_id' => $substitute->id,
            'priority' => 1,
        ]);

        Inventory::create([
            'product_id' => $substitute->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'DUP-001',
            'quantity' => 30,
            'onhand_qty' => 30,
            'hold_qty' => 0,
            'expiry_date' => now()->addMonth()->toDateString(),
            'is_archived' => false,
        ]);

        $suggestions = $this->service->suggestSubstitutes($original->id, $this->branch->id);

        $this->assertCount(1, $suggestions);
        $this->assertSame('explicit', $suggestions[0]['type']);
        $this->assertSame($substitute->id, $suggestions[0]['product']->id);
    }

    public function test_handles_string_quantity_corruption(): void
    {
        Log::spy();

        $calculateAvailableInventory = new ReflectionMethod($this->service, 'calculateAvailableInventory');
        $calculateAvailableInventory->setAccessible(true);

        $available = $calculateAvailableInventory->invoke(
            $this->service,
            collect([(object) [
                'id' => 1,
                'onhand_qty' => 'ABC123',
                'quantity' => 20,
                'hold_qty' => 0,
            ]]),
            101
        );

        $this->assertSame(0, $available);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message): bool {
            return $message === 'substitutions.invalid_onhand_quantity';
        })->once();
    }

    public function test_returns_array_when_validation_short_circuits(): void
    {
        $result = $this->service->suggestSubstitutes(0, $this->branch->id);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    private function createProduct(string $genericName, string $form, string $strength): Product
    {
        return Product::factory()->create([
            'generic_name' => $genericName,
            'brand_name' => $genericName.' Brand',
            'form' => $form,
            'strength' => $strength,
            'is_archived' => false,
        ]);
    }
}
