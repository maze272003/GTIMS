<?php

namespace Database\Factories;

use App\Models\IncomingRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomingRequestFactory extends Factory
{
    protected $model = IncomingRequest::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'requester_id' => User::factory(),
            'department' => $this->faker->word(),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'status' => 'draft',
            'remarks' => $this->faker->sentence(),
        ];
    }
}
