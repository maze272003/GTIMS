<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LowStockSetting;
use App\Models\Product;
use App\Models\Branch;

class LowStockSettingSeeder extends Seeder
{
    public function run(): void
    {
        // Create global default threshold
        LowStockSetting::updateOrCreate(
            ['is_global' => true],
            ['threshold' => 10, 'product_id' => null, 'branch_id' => null]
        );

        // Requires ProductSeeder and BranchSeeder to run first
        $products = Product::take(5)->get();
        $branches = Branch::all();

        if ($products->isEmpty() || $branches->isEmpty()) {
            return;
        }

        // Create product-specific overrides (no branch)
        foreach ($products->take(3) as $product) {
            LowStockSetting::updateOrCreate(
                ['product_id' => $product->id, 'branch_id' => null],
                ['threshold' => rand(15, 30), 'is_global' => false]
            );
        }

        // Create product+branch specific overrides
        foreach ($products->take(2) as $product) {
            foreach ($branches->take(2) as $branch) {
                LowStockSetting::updateOrCreate(
                    ['product_id' => $product->id, 'branch_id' => $branch->id],
                    ['threshold' => rand(20, 50), 'is_global' => false]
                );
            }
        }
    }
}
