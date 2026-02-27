<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $recordsPerModule = max(1, (int) config('tenancy.seeder.demo_records_per_module', 50));
        $barangaysPerProvince = max(1, (int) config('tenancy.seeder.demo_barangays_per_province', 5));

        $branch = Branch::query()->first() ?? Branch::query()->create(['name' => 'Main Branch']);
        $products = $this->ensureSharedProducts();

        if ($products->isEmpty()) {
            return;
        }

        $provinceSlugs = (array) config('tenancy.seeder.demo_provinces', ['bulacan', 'cebu', 'davao-del-sur']);
        $provinces = Province::query()
            ->whereIn('slug', array_map(fn ($slug) => Str::slug((string) $slug), $provinceSlugs))
            ->where('is_active', true)
            ->get();

        foreach ($provinces as $province) {
            $barangays = Barangay::query()
                ->where('province_id', $province->id)
                ->where('is_active', true)
                ->whereNotNull('slug')
                ->orderBy('id')
                ->take($barangaysPerProvince)
                ->get();

            foreach ($barangays as $barangay) {
                $this->seedInventoryRows($products, $branch->id, $province->id, $barangay->id, $recordsPerModule);
            }
        }
    }

    protected function ensureSharedProducts()
    {
        if (Product::query()->count() === 0) {
            $catalog = [
                ['brand_name' => 'Paracemol', 'generic_name' => 'Paracetamol', 'form' => 'Tablet', 'strength' => '500mg'],
                ['brand_name' => 'Ibupain', 'generic_name' => 'Ibuprofen', 'form' => 'Tablet', 'strength' => '200mg'],
                ['brand_name' => 'Amoxil', 'generic_name' => 'Amoxicillin', 'form' => 'Capsule', 'strength' => '250mg'],
                ['brand_name' => 'Claritin', 'generic_name' => 'Loratadine', 'form' => 'Tablet', 'strength' => '10mg'],
                ['brand_name' => 'Ventolin', 'generic_name' => 'Salbutamol', 'form' => 'Inhaler', 'strength' => '100mcg'],
            ];

            foreach ($catalog as $row) {
                Product::query()->firstOrCreate(
                    [
                        'generic_name' => $row['generic_name'],
                        'form' => $row['form'],
                        'strength' => $row['strength'],
                    ],
                    $row
                );
            }
        }

        return Product::query()->orderBy('id')->get();
    }

    protected function seedInventoryRows($products, int $branchId, int $provinceId, int $barangayId, int $records): void
    {
        $productCount = $products->count();
        if ($productCount === 0) {
            return;
        }

        for ($i = 1; $i <= $records; $i++) {
            $product = $products[($i - 1) % $productCount];
            $batch = sprintf('TENANT-%d-%d-%04d', $provinceId, $barangayId, $i);

            Inventory::query()->updateOrCreate(
                ['batch_number' => $batch],
                [
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'quantity' => 100 + $i,
                    'expiry_date' => now()->addMonths(6 + ($i % 18))->toDateString(),
                    'is_archived' => false,
                    'province_id' => $provinceId,
                    'barangay_id' => $barangayId,
                ]
            );
        }
    }
}

