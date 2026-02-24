<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BarangayFactory extends Factory
{
    protected $model = Barangay::class;

    public function definition(): array
    {
        $name = 'Brgy. ' . $this->faker->unique()->streetName();

        return [
            'barangay_name' => $name,
            'province_id' => Province::factory(),
            'slug' => Str::slug($name),
            'is_active' => true,
            'external_code' => null,
            'settings_json' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }
}
