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
            'patient_name' => $this->faker->name,
            'barangay_id' => fn () => Barangay::inRandomOrder()->first()?->id ?? Barangay::factory()->create()->id,
            'purok' => $this->faker->randomElement(['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5']),
            'category' => $this->faker->randomElement(['Adult', 'Child', 'Senior']),
            'date_dispensed' => $this->faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
        ];
    }
}