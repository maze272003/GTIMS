<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'    => Product::factory(),
            'branch_id'     => Branch::factory(),
            'batch_number'  => 'BATCH-' . $this->faker->unique()->numerify('####'),
            'quantity'      => $this->faker->numberBetween(10, 1000),
            'expiry_date'   => $this->faker->dateTimeBetween('+1 month', '+5 years')->format('Y-m-d'),
            'is_archived'   => false,
        ];
    }
}