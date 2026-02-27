<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Services\AnalyticsService;
use App\Tenancy\TenantResolver;
use Illuminate\Console\Command;

class TenantLoadTestCommand extends Command
{
    protected $signature = 'tenant:load-test
                            {--iterations=100 : Number of iterations per scenario}
                            {--concurrent=1 : Reserved for external parallel runners}';

    protected $description = 'Run lightweight multi-tenant load probes for route resolution and analytics.';

    public function __construct(
        protected TenantResolver $tenantResolver,
        protected AnalyticsService $analyticsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $barangay = Barangay::query()->with('province')->whereNotNull('slug')->first();

        if (!$barangay || !$barangay->province) {
            $this->error('No province/barangay slug pair found for load test.');
            return self::FAILURE;
        }

        $ctx = $this->tenantResolver->fromSlugs($barangay->province->slug, $barangay->slug);
        if (!$ctx) {
            $this->error('Unable to resolve tenant context from sample slugs.');
            return self::FAILURE;
        }

        $routeResolutionMs = $this->benchmark($iterations, function () use ($barangay) {
            $this->tenantResolver->fromSlugs($barangay->province->slug, $barangay->slug);
        });

        $analyticsMs = $this->benchmark($iterations, function () use ($ctx) {
            $this->analyticsService->getStockKPIs(null, $ctx);
        });

        $this->table(
            ['Scenario', 'Iterations', 'Total ms', 'Avg ms'],
            [
                ['route_resolution', $iterations, $routeResolutionMs['total'], $routeResolutionMs['avg']],
                ['analytics_kpis', $iterations, $analyticsMs['total'], $analyticsMs['avg']],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array{total:float, avg:float}
     */
    protected function benchmark(int $iterations, callable $callback): array
    {
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        $total = round((microtime(true) - $start) * 1000, 2);

        return [
            'total' => $total,
            'avg' => round($total / $iterations, 2),
        ];
    }
}
