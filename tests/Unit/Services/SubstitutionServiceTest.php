<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\ProductSubstitute;
use App\Services\SubstitutionService;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubstitutionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubstitutionService $service;
    protected $branch;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SubstitutionService(new AvailabilityService());
        
        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_get_equivalent_products_matches_generic_form_strength()
    {
        $original = Product::factory()->create([
            'generic_name' => 'Amoxicillin',
            'form' => 'Capsule',
            'strength' => '500mg',
            'brand_name' => 'Brand A',
        ]);

        $equivalent = Product::factory()->create([
            'generic_name' => 'Amoxicillin',
            'form' => 'Capsule',
            'strength' => '500mg',
            'brand_name' => 'Brand B',
        ]);

        $different = Product::factory()->create([
            'generic_name' => 'Amoxicillin',
            'form' => 'Syrup',
            'strength' => '250mg',
            'brand_name' => 'Brand C',
        ]);

        $results = $this->service->getEquivalentProducts($original->id);
        
        $this->assertTrue($results->contains('id', $equivalent->id));
        $this->assertFalse($results->contains('id', $different->id));
    }

    public function test_suggest_substitutes_returns_available_alternatives()
    {
        $original = Product::factory()->create([
            'generic_name' => 'Paracetamol',
            'form' => 'Tablet',
            'strength' => '500mg',
        ]);
        $substitute = Product::factory()->create([
            'generic_name' => 'Paracetamol',
            'form' => 'Tablet',
            'strength' => '500mg',
        ]);

        Inventory::create([
            'product_id' => $substitute->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'SUB-001',
            'quantity' => 50,
            'expiry_date' => now()->addYear(),
        ]);

        $suggestions = $this->service->suggestSubstitutes($original->id, $this->branch->id);

        $this->assertNotEmpty($suggestions);
        $this->assertEquals($substitute->id, $suggestions[0]['product']->id);
    }

    public function test_explicit_substitutes_returned_first()
    {
        $original = Product::factory()->create();
        $explicitSub = Product::factory()->create();

        ProductSubstitute::create([
            'product_id' => $original->id,
            'substitute_product_id' => $explicitSub->id,
            'priority' => 1,
        ]);

        Inventory::create([
            'product_id' => $explicitSub->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'EXP-001',
            'quantity' => 30,
            'expiry_date' => now()->addYear(),
        ]);

        $suggestions = $this->service->suggestSubstitutes($original->id, $this->branch->id);

        $this->assertNotEmpty($suggestions);
        $this->assertEquals('explicit', $suggestions[0]['type']);
    }
}
