<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\Product;
use App\Repositories\Eloquent\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductRepository(new Product());
    }

    public function test_get_active_products(): void
    {
        Product::factory()->create(['is_archived' => false]);
        Product::factory()->create(['is_archived' => false]);
        Product::factory()->create(['is_archived' => true]);

        $active = $this->repository->getActive();

        $this->assertCount(2, $active);
    }

    public function test_create_product(): void
    {
        $product = $this->repository->create(
            Product::factory()->make()->toArray()
        );

        $this->assertNotNull($product->id);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_find_product(): void
    {
        $product = Product::factory()->create();

        $found = $this->repository->find($product->id);

        $this->assertNotNull($found);
        $this->assertEquals($product->name, $found->name);
    }
}
