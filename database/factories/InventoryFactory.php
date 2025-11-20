<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    public function definition(): array
    {
        // Randomly pick RHU 1 or RHU 2
        $branch = Branch::whereIn('name', ['RHU 1', 'RHU 2'])->inRandomOrder()->first();

        return [
            'product_id'    => Product::inRandomOrder()->first()->id ?? Product::factory(),
            'branch_id'     => $branch?->id,
            'batch_number'  => 'BATCH-' . $this->faker->unique()->numerify('####'),
            'quantity'      => $this->faker->numberBetween(10, 1000),
            'expiry_date'   => $this->faker->dateTimeBetween('+1 month', '+5 years')->format('Y-m-d'),
            'is_archived'   => false, // better to use boolean, not 2
        ];
    }
}