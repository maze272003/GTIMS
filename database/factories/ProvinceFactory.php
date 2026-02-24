<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->city() . ' Province';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => strtoupper($this->faker->bothify('??-###')),
            'is_active' => true,
            'settings_json' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }
}
