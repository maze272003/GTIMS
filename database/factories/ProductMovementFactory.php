<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductMovementFactory extends Factory
{
    protected $model = ProductMovement::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['IN', 'OUT']);
        $qty = $this->faker->numberBetween(10, 50);
        $before = $this->faker->numberBetween(100, 200);

        if ($type === 'IN') {
            $after = $before + $qty;
        } else {
            $after = $before - $qty;
        }

        return [
            'product_id' => Product::factory(),
            'inventory_id' => Inventory::factory(),
            'user_id' => User::factory(),
            'type' => $type,
            'quantity' => $qty,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'description' => $this->faker->sentence,
            'created_at' => $this->faker->dateTimeBetween('-3 years', 'now'), // Important for charts
            'updated_at' => fn (array $attributes) => $attributes['created_at'],
        ];
    }
}
