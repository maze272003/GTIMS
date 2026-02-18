<?php

namespace Database\Factories;

use App\Models\Hold;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HoldFactory extends Factory
{
    protected $model = Hold::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'type' => $this->faker->randomElement(['reservation', 'quarantine', 'recall']),
            'reason_code' => $this->faker->word(),
            'remarks' => $this->faker->sentence(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'status' => 'pending',
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
