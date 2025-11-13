<?php

namespace Database\Seeders;

use App\Models\Dispensedmedication;
use App\Models\Inventory;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatientRecordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all encoders and admins
        $users = User::whereIn('user_level_id', [2, 3])->get();
        if ($users->isEmpty()) {
            $this->command->info('No admin or encoder users found. Seeding a default user.');
            $users = collect([User::factory()->withLevel(3)->create()]);
        }

        // Get all available inventory batches with enough stock
        $inventories = Inventory::where('quantity', '>', 100)
            ->where('is_archived', 2)
            ->whereDate('expiry_date', '>', now()->addMonth())
            ->with('product') // Eager load product
            ->get();

        if ($inventories->isEmpty()) {
            $this->command->error('No valid inventory found. Please run InventorySeeder first.');
            return;
        }

        $this->command->info('Starting to seed 500 patient records. This may take a moment...');

        // Create 500 patient records
        Patientrecords::factory()->count(10)->create()->each(function ($record) use ($users, $inventories) {
            
            // Each patient gets 1 to 3 medications
            $medicationCount = rand(1, 3);
            
            for ($i = 0; $i < $medicationCount; $i++) {
                
                // Find a random inventory item
                $inventory = $inventories->random();
                $product = $inventory->product;

                // Ensure we don't dispense more than available
                $quantityToDispense = rand(5, min(30, $inventory->quantity));
                
                // If stock is somehow 0, skip
                if ($inventory->quantity <= 0) {
                    continue; 
                }

                $quantity_before = $inventory->quantity;
                $quantity_after = $inventory->quantity - $quantityToDispense;
                
                // 1. Create the Dispensed Medication record
                Dispensedmedication::factory()->create([
                    'patientrecord_id' => $record->id,
                    'barangay_id' => $record->barangay_id,
                    'batch_number' => $inventory->batch_number,
                    'generic_name' => $product->generic_name,
                    'brand_name' => $product->brand_name,
                    'strength' => $product->strength,
                    'form' => $product->form,
                    'quantity' => $quantityToDispense,
                ]);

                // 2. Update the inventory quantity
                $inventory->quantity = $quantity_after;
                $inventory->save();

                // 3. Create the "OUT" Product Movement log
                ProductMovement::factory()->create([
                    'product_id' => $product->id,
                    'inventory_id' => $inventory->id,
                    'user_id' => $users->random()->id,
                    'type' => 'OUT',
                    'quantity' => $quantityToDispense,
                    'quantity_before' => $quantity_before,
                    'quantity_after' => $quantity_after,
                    'description' => "Dispensed to Patient: {$record->patient_name} (Record: #{$record->id})",
                    'created_at' => $record->date_dispensed, // Match the patient's dispensation date
                ]);

                // Update the collection to reflect new quantity, so we don't oversell
                $inventories = $inventories->map(function($item) use ($inventory, $quantity_after) {
                    if ($item->id === $inventory->id) {
                        $item->quantity = $quantity_after;
                    }
                    return $item;
                })->filter(function($item) {
                    return $item->quantity > 0; // Remove if empty
                });
            }
        });

        $this->command->info('Finished seeding 500 patient records.');
    }
}