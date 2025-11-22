<?php

namespace Database\Seeders;

use App\Models\Dispensedmedication;
use App\Models\Inventory;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class PatientRecordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get Encoders/Admins for the logs
        $users = User::whereIn('user_level_id', [2, 3])->get();
        if ($users->isEmpty()) {
            $this->command->info('No admin or encoder users found. Seeding a default user.');
            $users = collect([User::factory()->withLevel(3)->create()]);
        }

        // 2. Get Branches (Ensure RHU 1 and RHU 2 exist)
        $branches = Branch::all();
        if ($branches->isEmpty()) {
             $this->command->info('No branches found. Creating default branches.');
             $branches = collect([
                 Branch::create(['name' => 'RHU 1']),
                 Branch::create(['name' => 'RHU 2']),
             ]);
        }

        // 3. Get Inventory (Only items with stock)
        $inventories = Inventory::where('quantity', '>', 0)
            ->where('is_archived', 2)
            ->whereDate('expiry_date', '>', now()->addMonth())
            ->with('product')
            ->get();

        if ($inventories->isEmpty()) {
            $this->command->error('No valid inventory found. Please run InventorySeeder first.');
            return;
        }

        $this->command->info('Starting to seed 500 patient records...');

        // 4. Create 500 Patient Records
        Patientrecords::factory()->count(500)->create()->each(function ($record) use ($users, $inventories, $branches) {
            
            // === A. ASSIGN BRANCH (RHU 1 or RHU 2) ===
            $randomBranch = $branches->random();
            $record->branch_id = $randomBranch->id;
            $record->save();

            // === B. DISPENSE MEDICATIONS ===
            // Decide how many meds this patient gets (1 to 3 types)
            $medicationCount = rand(1, 3);
            
            for ($i = 0; $i < $medicationCount; $i++) {
                
                // Filter inventory to find items that STILL have stock > 0
                // We filter the main collection every time to ensure we don't pick an empty item
                $available_inventory = $inventories->where('quantity', '>', 0);

                // CRITICAL FIX: If no meds are left, stop the loop to avoid "InvalidArgumentException"
                if ($available_inventory->isEmpty()) {
                    break; 
                }

                // Pick a random item from the AVAILABLE list
                $inventory = $available_inventory->random();
                $product = $inventory->product;

                // Determine quantity (1 to 30, but not more than what's left)
                $maxQty = min(30, $inventory->quantity);
                $quantityToDispense = rand(1, $maxQty);
                
                $quantity_before = $inventory->quantity;
                $quantity_after = $quantity_before - $quantityToDispense;
                
                // 1. Create Dispensed Medication Record
                Dispensedmedication::factory()->create([
                    'patientrecord_id' => $record->id,
                    'barangay_id'      => $record->barangay_id,
                    'batch_number'     => $inventory->batch_number,
                    'generic_name'     => $product->generic_name,
                    'brand_name'       => $product->brand_name,
                    'strength'         => $product->strength,
                    'form'             => $product->form,
                    'quantity'         => $quantityToDispense,
                ]);

                // 2. Update Inventory (Updates the object in memory AND the database)
                $inventory->quantity = $quantity_after;
                $inventory->save();

                // 3. Log Movement
                ProductMovement::factory()->create([
                    'product_id'      => $product->id,
                    'inventory_id'    => $inventory->id,
                    'user_id'         => $users->random()->id,
                    'type'            => 'OUT',
                    'quantity'        => $quantityToDispense,
                    'quantity_before' => $quantity_before,
                    'quantity_after'  => $quantity_after,
                    'description'     => "Dispensed to Patient: {$record->patient_name} (Record: #{$record->id})",
                    'created_at'      => $record->date_dispensed,
                ]);
            }
        });

        $this->command->info('Finished seeding 500 patient records.');
    }
}