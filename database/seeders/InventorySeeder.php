<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // Ensure products exist before seeding inventory
        if (Product::count() === 0) {
            $this->call(ProductSeeder::class);
        }

        $products = Product::all();

        foreach (range(1, 20) as $index) {
            Inventory::create([
                'product_id' => $products->random()->id,
                'batch_number' => 'BATCH-' . str_pad($index, 4, '0', STR_PAD_LEFT),
                'quantity' => rand(10, 1000),
                'expiry_date' => now()->addMonths(rand(1, 24))->format('Y-m-d'),
                'is_archived' => 2,
            ]);
        }
    }
}