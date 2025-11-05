<?php

namespace Database\Factories;

use App\Models\Dispensedmedication;
use App\Models\Patientrecords;
use App\Models\Barangay;
use Illuminate\Database\Eloquent\Factories\Factory;

class DispensedmedicationFactory extends Factory
{
    protected $model = Dispensedmedication::class;

    public function definition(): array
    {
        return [
            'patientrecord_id' => Patientrecords::factory(),
            'barangay_id' => fn() => Barangay::inRandomOrder()->first()->id ?? Barangay::factory()->create()->id,
            'batch_number' => 'B-' . $this->faker->numberBetween(1000, 9000),
            'generic_name' => 'Paracetamol', // Placeholder, will be overridden in seeder
            'brand_name' => 'Biogesic', // Placeholder, will be overridden in seeder
            'strength' => '500mg', // Placeholder
            'form' => 'Tablet', // Placeholder
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
