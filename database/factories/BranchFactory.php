<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true).' Branch';

        return [
            'name' => $name,
            'code' => $this->faker->unique()->slug(2),
            'is_main' => false,
            'is_archived' => false,
        ];
    }
}
