<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Repositories\Eloquent\SupplierRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected SupplierRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SupplierRepository(new Supplier());
    }

    public function test_create_supplier(): void
    {
        $supplier = $this->repository->create([
            'name' => 'Test Supplier',
            'contact_person' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        $this->assertDatabaseHas('suppliers', ['name' => 'Test Supplier']);
        $this->assertEquals('Test Supplier', $supplier->name);
    }

    public function test_find_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Find Test',
            'email' => 'find@example.com',
        ]);

        $found = $this->repository->find($supplier->id);
        $this->assertNotNull($found);
        $this->assertEquals('Find Test', $found->name);
    }

    public function test_update_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $result = $this->repository->update($supplier->id, ['name' => 'New Name']);

        $this->assertTrue($result);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
    }

    public function test_delete_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Delete Test',
        ]);

        $result = $this->repository->delete($supplier->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_paginate_with_product_count(): void
    {
        Supplier::create(['name' => 'Supplier A']);
        Supplier::create(['name' => 'Supplier B']);

        $paginated = $this->repository->paginateWithProductCount(10);

        $this->assertCount(2, $paginated->items());
    }

    public function test_find_with_products(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier With Products']);
        $product = Product::factory()->create();

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'lead_time_days' => 5,
        ]);

        $found = $this->repository->findWithProducts($supplier->id);
        $this->assertTrue($found->relationLoaded('products'));
        $this->assertCount(1, $found->products);
    }

    public function test_link_product(): void
    {
        $supplier = Supplier::create(['name' => 'Linker']);
        $product = Product::factory()->create();

        $this->repository->linkProduct($supplier->id, $product->id, 7, 15.50);

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'lead_time_days' => 7,
        ]);
    }

    public function test_unlink_product(): void
    {
        $supplier = Supplier::create(['name' => 'Unlinker']);
        $product = Product::factory()->create();

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'lead_time_days' => 3,
        ]);

        $this->repository->unlinkProduct($supplier->id, $product->id);

        $this->assertDatabaseMissing('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
        ]);
    }
}
