<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\ProductMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that adding NEW stock creates an 'IN' movement record.
     */
    public function test_add_stock_new_batch_creates_movement_log()
    {
        $this->withoutExceptionHandling();
        // 1. Create a user and product
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'generic_name' => 'Paracetamol', 
            'brand_name' => 'Biogesic',
            'is_archived' => 1
        ]);

        // 2. Simulate the Form Data
        $formData = [
            'product_id' => $product->id,
            'branch_id' => 1, // RHU 1
            'batchnumber' => 'BATCH-001',
            'quantity' => 100,
            'expiry' => now()->addYear()->format('Y-m-d'),
        ];

        // 3. Call the Route (Assuming route name is 'admin.inventory.addStock')
        // Adjust the route name if yours is different in web.php
        $response = $this->actingAs($user)
                         ->post(route('admin.inventory.addStock'), $formData);

        // 4. Assert Redirect & Success
        $response->assertRedirect(route('admin.inventory'));
        $response->assertSessionHas('success');

        // 5. Assert Inventory was created
        $this->assertDatabaseHas('inventories', [
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
        ]);

        // 6. ASSERT PRODUCT MOVEMENT WAS CREATED
        $this->assertDatabaseHas('product_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'IN',
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
            'description' => 'Manual stock addition (new batch)',
        ]);
    }

    /**
     * Test that adding to EXISTING stock creates an 'IN' movement record.
     */
    public function test_add_stock_existing_batch_creates_movement_log()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        // Create existing inventory
        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => 1,
            'batch_number' => 'BATCH-EXISTING',
            'quantity' => 50,
            'expiry_date' => '2026-01-01',
            'is_archived' => 2
        ]);

        $formData = [
            'product_id' => $product->id,
            'branch_id' => 1,
            'batchnumber' => 'BATCH-EXISTING',
            'quantity' => 20, // Adding 20 more
            'expiry' => '2026-01-01',
        ];

        $this->actingAs($user)->post(route('admin.inventory.addStock'), $formData);

        // Assert Inventory Updated to 70
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 70,
        ]);

        // ASSERT MOVEMENT LOG
        $this->assertDatabaseHas('product_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'IN',
            'quantity' => 20, // The added amount
            'quantity_before' => 50,
            'quantity_after' => 70,
            'description' => 'Manual stock addition (existing batch)',
        ]);
    }

    /**
     * Test that editing stock quantity creates a movement log.
     */
    public function test_edit_stock_updates_quantity_and_logs_movement()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        // Start with 100 items
        $inventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => 1,
            'batch_number' => 'BATCH-EDIT',
            'quantity' => 100,
            'expiry_date' => '2026-01-01',
            'is_archived' => 2
        ]);

        // Change quantity to 80 (Remove 20)
        $formData = [
            'inventory_id' => $inventory->id,
            'batchnumber' => 'BATCH-EDIT',
            'quantity' => 80, 
            'expiry' => '2026-01-01',
        ];

        $this->actingAs($user)->post(route('admin.inventory.editStock'), $formData);

        // Assert Movement Log (Should be OUT because quantity decreased)
        $this->assertDatabaseHas('product_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'OUT',
            'quantity' => 20, // 100 - 80 = 20 diff
            'quantity_before' => 100,
            'quantity_after' => 80,
            'description' => 'Manual stock adjustment (remove)',
        ]);
    }

    /**
     * Test Transferring Stock creates TWO logs (one OUT from source, one IN to dest).
     */
    public function test_transfer_stock_creates_two_movements()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        // Source: RHU 1 has 50 items
        $sourceInventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => 1,
            'batch_number' => 'BATCH-TRANS',
            'quantity' => 50,
            'expiry_date' => '2026-01-01',
            'is_archived' => 2
        ]);

        $formData = [
            'inventory_id' => $sourceInventory->id,
            'quantity' => 10, // Transfer 10
            'destination_branch' => 2, // Send to RHU 2
        ];

        $this->actingAs($user)->post(route('admin.inventory.transferStock'), $formData);

        // 1. Check Source Inventory reduced
        $this->assertDatabaseHas('inventories', [
            'id' => $sourceInventory->id,
            'quantity' => 40,
        ]);

        // 2. Check Destination Inventory created
        $this->assertDatabaseHas('inventories', [
            'branch_id' => 2,
            'batch_number' => 'BATCH-TRANS',
            'quantity' => 10,
        ]);

        // 3. Check Movement OUT (Source)
        $this->assertDatabaseHas('product_movements', [
            'product_id' => $product->id,
            'inventory_id' => $sourceInventory->id,
            'type' => 'OUT',
            'quantity' => 10,
            'description' => 'Stock transfer from RHU 1 to RHU 2.',
        ]);

        // 4. Check Movement IN (Destination)
        // We define the query loosely here because we don't have the ID of the new inventory handy without querying first
        $this->assertDatabaseHas('product_movements', [
            'product_id' => $product->id,
            'type' => 'IN',
            'quantity' => 10,
            'description' => 'Stock received from RHU 1 to RHU 2.',
        ]);
    }
}