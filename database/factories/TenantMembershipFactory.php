<?php

namespace Database\Factories;

use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'scope_type' => 'barangay',
            'scope_id' => null,
            'is_primary' => true,
            'status' => 'active',
        ];
    }

    public function platform(): static
    {
        return $this->state(fn() => [
            'scope_type' => 'platform',
            'scope_id' => null,
        ]);
    }

    public function province(int $provinceId): static
    {
        return $this->state(fn() => [
            'scope_type' => 'province',
            'scope_id' => $provinceId,
        ]);
    }

    public function barangay(int $barangayId): static
    {
        return $this->state(fn() => [
            'scope_type' => 'barangay',
            'scope_id' => $barangayId,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn() => ['status' => 'suspended']);
    }
}
