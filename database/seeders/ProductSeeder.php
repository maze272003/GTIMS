<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'brand_name' => 'Paracemol',
                'generic_name' => 'Paracetamol',
                'form' => 'Tablet',
                'strength' => '500mg',
            ],
            [
                'brand_name' => 'Ibupain',
                'generic_name' => 'Ibuprofen',
                'form' => 'Tablet',
                'strength' => '200mg',
            ],
            [
                'brand_name' => 'Amoxil',
                'generic_name' => 'Amoxicillin',
                'form' => 'Capsule',
                'strength' => '250mg',
            ],
            [
                'brand_name' => 'Zithro',
                'generic_name' => 'Azithromycin',
                'form' => 'Tablet',
                'strength' => '500mg',
            ],
            [
                'brand_name' => 'Ciproxin',
                'generic_name' => 'Ciprofloxacin',
                'form' => 'Tablet',
                'strength' => '500mg',
            ],
            [
                'brand_name' => 'Claritin',
                'generic_name' => 'Loratadine',
                'form' => 'Tablet',
                'strength' => '10mg',
            ],
            [
                'brand_name' => 'Ventolin',
                'generic_name' => 'Salbutamol',
                'form' => 'Inhaler',
                'strength' => '100mcg',
            ],
            [
                'brand_name' => 'Lipitor',
                'generic_name' => 'Atorvastatin',
                'form' => 'Tablet',
                'strength' => '20mg',
            ],
            [
                'brand_name' => 'Zocor',
                'generic_name' => 'Simvastatin',
                'form' => 'Tablet',
                'strength' => '40mg',
            ],
            [
                'brand_name' => 'Metrogel',
                'generic_name' => 'Metronidazole',
                'form' => 'Gel',
                'strength' => '0.75%',
            ],
            [
                'brand_name' => 'Augmentin',
                'generic_name' => 'Amoxicillin',
                'form' => 'Tablet',
                'strength' => '625mg',
            ],
            [
                'brand_name' => 'Doxycen',
                'generic_name' => 'Doxycycline',
                'form' => 'Capsule',
                'strength' => '100mg',
            ],
            [
                'brand_name' => 'Zantac',
                'generic_name' => 'Ranitidine',
                'form' => 'Tablet',
                'strength' => '150mg',
            ],
            [
                'brand_name' => 'Nexium',
                'generic_name' => 'Esomeprazole',
                'form' => 'Capsule',
                'strength' => '40mg',
            ],
            [
                'brand_name' => 'Voltaren',
                'generic_name' => 'Diclofenac',
                'form' => 'Gel',
                'strength' => '1%',
            ],
            [
                'brand_name' => 'Singulair',
                'generic_name' => 'Montelukast',
                'form' => 'Tablet',
                'strength' => '10mg',
            ],
            [
                'brand_name' => 'Crestor',
                'generic_name' => 'Rosuvastatin',
                'form' => 'Tablet',
                'strength' => '10mg',
            ],
            [
                'brand_name' => 'Flonase',
                'generic_name' => 'Fluticasone',
                'form' => 'Nasal Spray',
                'strength' => '50mcg',
            ],
            [
                'brand_name' => 'Advair',
                'generic_name' => 'Fluticasone',
                'form' => 'Inhaler',
                'strength' => '250/50mcg',
            ],
            [
                'brand_name' => 'Xanax',
                'generic_name' => 'Alprazolam',
                'form' => 'Tablet',
                'strength' => '0.5mg',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}