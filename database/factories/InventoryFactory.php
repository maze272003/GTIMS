<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;


    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_number' => $this->faker->unique()->bothify('BATCH-####'),
            // 'quantity' => $this->faker->numberBetween(1, 500),
            'quantity' => rand(1, 500),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+1 month', '+2 years,')->format('Y-m-d'),
        ];
    }
}