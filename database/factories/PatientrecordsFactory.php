<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Patientrecords;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientrecordsFactory extends Factory
{
    protected $model = Patientrecords::class;

    public function definition(): array
    {
        return [
            'patient_name'   => fake()->name(),
            'barangay_id'    => Barangay::inRandomOrder()->first() ?? Barangay::factory()->create(),
            'purok'          => fake()->randomElement(['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5']),
            'category'       => fake()->randomElement(['Adult', 'Child', 'Senior']),
            'date_dispensed' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
        ];
    }
}