<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantQueueDeploymentValidateCommand extends Command
{
    protected $signature = 'tenant:queue:validate-deploy';

    protected $description = 'Validate queue drain/restart deployment prerequisites for tenant-aware jobs.';

    public function handle(): int
    {
        $checks = [
            'jobs_table_exists' => Schema::hasTable('jobs'),
            'failed_jobs_table_exists' => Schema::hasTable('failed_jobs'),
            'queue_connection' => $this->queueConnectionReachable(),
            'tenant_job_middleware_present' => class_exists(\App\Jobs\Middleware\SetTenantContextMiddleware::class),
        ];

        $this->table(
            ['Check', 'Status'],
            collect($checks)->map(fn ($ok, $name) => [$name, $ok ? 'OK' : 'FAIL'])->all()
        );

        if (in_array(false, $checks, true)) {
            $this->error('Queue deployment validation failed. Review runbook before deploy.');
            return self::FAILURE;
        }

        $this->info('Queue deployment validation passed.');
        $this->line('Recommended procedure: pause workers -> queue:work --stop-when-empty -> deploy -> restart workers -> verify failed jobs.');
        return self::SUCCESS;
    }

    protected function queueConnectionReachable(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

