<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'brand_name' => $this->faker->company . ' ' . $this->faker->word,
            'generic_name' => $this->faker->word,
            'form' => $this->faker->randomElement(['Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Ointment']),
            'strength' => $this->faker->randomElement(['5mg', '10mg', '20mg', '50mg', '100mg', '250mg', '500mg']),
        ];
    }
}