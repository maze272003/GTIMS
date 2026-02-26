<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Models\TenantUsage;
use Illuminate\Console\Command;

class TenantUsageReportCommand extends Command
{
    protected $signature = 'tenant:usage:report
                            {--province= : Report for a specific province by ID}
                            {--period= : Report period (current month by default, format: YYYY-MM)}';

    protected $description = 'Generate tenant usage report';

    public function handle(): int
    {
        $provinceId = $this->option('province');
        $period = $this->option('period') ?? now()->format('Y-m');

        $periodStart = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();

        $query = TenantUsage::where('period_start', $periodStart);

        if ($provinceId) {
            $query->where('province_id', $provinceId);
            $province = Province::find($provinceId);
            $this->info("Usage report for province: " . ($province?->name ?? "#{$provinceId}"));
        } else {
            $this->info("Usage report for all tenants");
        }

        $this->info("Period: {$period}");
        $this->newLine();

        $records = $query->orderBy('province_id')
            ->orderBy('barangay_id')
            ->orderBy('metric_key')
            ->get();

        if ($records->isEmpty()) {
            $this->warn('No usage data found for the specified period.');
            return self::SUCCESS;
        }

        $headers = ['Province ID', 'Barangay ID', 'Metric', 'Value', 'Period Start', 'Period End'];
        $rows = $records->map(fn ($r) => [
            $r->province_id,
            $r->barangay_id ?? '-',
            $r->metric_key,
            number_format($r->metric_value),
            $r->period_start,
            $r->period_end,
        ])->toArray();

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
