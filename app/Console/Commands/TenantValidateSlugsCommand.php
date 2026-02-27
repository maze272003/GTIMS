<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantValidateSlugsCommand extends Command
{
    protected $signature = 'tenant:validate-slugs';

    protected $description = 'Validate canonical province/barangay slugs and detect reserved collisions.';

    public function handle(): int
    {
        $reserved = [
            strtolower((string) config('tenancy.moderator_prefix', 'moderator')),
            'admin',
            'api',
        ];

        $invalidProvinces = Province::query()
            ->where(function ($query) use ($reserved) {
                foreach ($reserved as $slug) {
                    $query->orWhereRaw('LOWER(slug) = ?', [$slug]);
                }
            })
            ->pluck('slug', 'id');

        $duplicateBarangays = Barangay::query()
            ->select('province_id', 'slug', DB::raw('COUNT(*) as total'))
            ->groupBy('province_id', 'slug')
            ->having('total', '>', 1)
            ->get();

        if ($invalidProvinces->isNotEmpty()) {
            $this->error('Reserved province slugs found:');
            foreach ($invalidProvinces as $id => $slug) {
                $this->line("- Province #{$id} uses reserved slug '{$slug}'");
            }
        }

        if ($duplicateBarangays->isNotEmpty()) {
            $this->error('Duplicate barangay slugs within provinces found:');
            foreach ($duplicateBarangays as $row) {
                $this->line("- Province #{$row->province_id}, slug '{$row->slug}' appears {$row->total} times");
            }
        }

        if ($invalidProvinces->isEmpty() && $duplicateBarangays->isEmpty()) {
            $this->info('Slug validation passed. No reserved/duplicate collisions found.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
