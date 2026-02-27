<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProvinceBarangaySeeder extends Seeder
{
    public function run(): void
    {
        $chunkSize = max(100, (int) config('tenancy.seeder.chunk_size', 500));
        $seedAllGeo = (bool) config('tenancy.seeder.seed_all_geo', true);

        if ($seedAllGeo && $this->seedFromPsgc($chunkSize)) {
            $this->normalizeLegacyBarangays();
            $this->fixDuplicateSlugs();
            return;
        }

        $this->command?->warn('PSGC seeding unavailable. Falling back to local province/barangay bootstrap.');
        $this->seedFallbackGeo();
        $this->normalizeLegacyBarangays();
        $this->fixDuplicateSlugs();
    }

    protected function seedFromPsgc(int $chunkSize): bool
    {
        $baseUrl = rtrim((string) config('tenancy.seeder.psgc_base_url', 'https://psgc.gitlab.io/api'), '/');
        $isActive = (bool) config('tenancy.seeder.activate_seeded_geo', true);

        try {
            $provincePayload = Http::timeout(180)
                ->retry(2, 600)
                ->acceptJson()
                ->get("{$baseUrl}/provinces/")
                ->throw()
                ->json();

            if (!is_array($provincePayload) || empty($provincePayload)) {
                return false;
            }

            $provinceRows = collect($provincePayload)
                ->filter(fn ($row) => is_array($row) && !empty($row['name']) && !empty($row['code']))
                ->map(function (array $row) use ($isActive) {
                    $name = trim((string) $row['name']);
                    $code = trim((string) $row['code']);
                    $slug = Str::slug($name);
                    if ($slug === '') {
                        $slug = 'province-' . $code;
                    }

                    return [
                        'name' => $name,
                        'slug' => $slug,
                        'code' => $code,
                        'is_active' => $isActive,
                        'settings_json' => null,
                    ];
                })
                ->values();

            foreach ($provinceRows as $provinceRow) {
                Province::query()->updateOrCreate(
                    ['code' => $provinceRow['code']],
                    $provinceRow
                );
            }

            $provinceIdByCode = Province::query()
                ->whereIn('code', $provinceRows->pluck('code')->all())
                ->pluck('id', 'code')
                ->all();

            $barangayPayload = Http::timeout(240)
                ->retry(2, 800)
                ->acceptJson()
                ->get("{$baseUrl}/barangays/")
                ->throw()
                ->json();

            if (!is_array($barangayPayload) || empty($barangayPayload)) {
                return false;
            }

            $now = now();
            $buffer = [];
            $seeded = 0;

            foreach ($barangayPayload as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $provinceCode = trim((string) ($row['provinceCode'] ?? ''));
                $barangayCode = trim((string) ($row['code'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));

                if ($barangayCode === '' || $name === '') {
                    continue;
                }

                if ($provinceCode === '') {
                    $fallback = $this->fallbackProvinceFromRegion($row);
                    if ($fallback !== null) {
                        $provinceCode = $fallback['code'];
                        if (!isset($provinceIdByCode[$provinceCode])) {
                            $province = Province::query()->updateOrCreate(
                                ['code' => $fallback['code']],
                                [
                                    'name' => $fallback['name'],
                                    'slug' => $fallback['slug'],
                                    'is_active' => $isActive,
                                    'settings_json' => null,
                                ]
                            );
                            $provinceIdByCode[$provinceCode] = $province->id;
                        }
                    }
                }

                if ($provinceCode === '') {
                    continue;
                }

                $provinceId = $provinceIdByCode[$provinceCode] ?? null;
                if (!$provinceId) {
                    continue;
                }

                $slugBase = Str::slug($name);
                if ($slugBase === '') {
                    $slugBase = 'barangay';
                }

                $buffer[] = [
                    'province_id' => $provinceId,
                    'barangay_name' => $name,
                    'slug' => "{$slugBase}-{$barangayCode}",
                    'is_active' => $isActive,
                    'external_code' => $barangayCode,
                    'settings_json' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($buffer) >= $chunkSize) {
                    DB::table('barangays')->upsert(
                        $buffer,
                        ['province_id', 'slug'],
                        ['barangay_name', 'is_active', 'external_code', 'settings_json', 'updated_at']
                    );

                    $seeded += count($buffer);
                    $buffer = [];
                }
            }

            if (!empty($buffer)) {
                DB::table('barangays')->upsert(
                    $buffer,
                    ['province_id', 'slug'],
                    ['barangay_name', 'is_active', 'external_code', 'settings_json', 'updated_at']
                );

                $seeded += count($buffer);
            }

            $totalProvinces = Province::query()->count();
            $this->command?->info(
                "ProvinceBarangaySeeder: seeded/updated {$provinceRows->count()} PSGC provinces ({$totalProvinces} total with fallbacks) and {$seeded} barangay rows."
            );
            return true;
        } catch (\Throwable $e) {
            $this->command?->warn('ProvinceBarangaySeeder PSGC source failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function seedFallbackGeo(): void
    {
        $isActive = (bool) config('tenancy.seeder.activate_seeded_geo', true);
        $demoProvinceSlugs = (array) config('tenancy.seeder.demo_provinces', ['bulacan']);

        if (empty($demoProvinceSlugs)) {
            $demoProvinceSlugs = ['bulacan'];
        }

        foreach ($demoProvinceSlugs as $index => $slug) {
            $slug = Str::slug((string) $slug);
            if ($slug === '') {
                continue;
            }

            Province::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::title(str_replace('-', ' ', $slug)),
                    'code' => strtoupper(sprintf('FB-%03d', $index + 1)),
                    'is_active' => $isActive,
                    'settings_json' => null,
                ]
            );
        }

        if (!Province::query()->exists()) {
            Province::query()->create([
                'name' => 'Default Province',
                'slug' => 'default-province',
                'code' => 'FB-000',
                'is_active' => $isActive,
                'settings_json' => null,
            ]);
        }
    }

    protected function normalizeLegacyBarangays(): void
    {
        $defaultProvince = Province::query()->orderBy('id')->first();
        if (!$defaultProvince) {
            return;
        }

        Barangay::query()
            ->where(function ($query) {
                $query->whereNull('province_id')
                    ->orWhereNull('slug')
                    ->orWhere('slug', '');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($defaultProvince) {
                foreach ($rows as $barangay) {
                    $provinceId = $barangay->province_id ?: $defaultProvince->id;
                    $name = trim((string) $barangay->barangay_name);
                    if ($name === '') {
                        $name = 'Barangay';
                    }

                    $base = Str::slug($name);
                    if ($base === '') {
                        $base = 'barangay';
                    }

                    $slug = "{$base}-{$barangay->id}";

                    $barangay->forceFill([
                        'province_id' => $provinceId,
                        'slug' => $slug,
                        'is_active' => $barangay->is_active ?? true,
                    ])->save();
                }
            });
    }

    protected function fixDuplicateSlugs(): void
    {
        $duplicates = Barangay::query()
            ->select('province_id', 'slug', DB::raw('COUNT(*) as total'))
            ->whereNotNull('province_id')
            ->whereNotNull('slug')
            ->groupBy('province_id', 'slug')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $records = Barangay::query()
                ->where('province_id', $duplicate->province_id)
                ->where('slug', $duplicate->slug)
                ->orderBy('id')
                ->get();

            $first = true;
            foreach ($records as $barangay) {
                if ($first) {
                    $first = false;
                    continue;
                }

                $barangay->forceFill([
                    'slug' => "{$duplicate->slug}-{$barangay->id}",
                ])->save();
            }
        }
    }

    /**
     * @return array{code:string,name:string,slug:string}|null
     */
    protected function fallbackProvinceFromRegion(array $row): ?array
    {
        $regionCode = trim((string) ($row['regionCode'] ?? ''));
        if ($regionCode === '') {
            return null;
        }

        if ($regionCode === '130000000') {
            return [
                'code' => $regionCode,
                'name' => 'National Capital Region',
                'slug' => 'national-capital-region',
            ];
        }

        $slug = Str::slug('region-' . $regionCode);

        return [
            'code' => $regionCode,
            'name' => 'Region ' . $regionCode,
            'slug' => $slug !== '' ? $slug : 'region-' . $regionCode,
        ];
    }
}
