<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Barangay;

class BarangaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $barangay = [
            "Bago",
            "Concepcion",
            "Nazareth",
            "Padolina",
            "Palale",
            "Pias",
            "Poblacion Central",
            "Poblacion East",
            "Poblacion West",
            "Pulong Matong",
            "Rio Chico",
            "Sampaguita",
            "San Pedro (Pob.)"
        ];

        foreach ($barangay as $barangay) {
            Barangay::create([
                'barangay_name' => $barangay
            ]);
        }
    }
}
