<?php

namespace App\Jobs\Middleware;

use App\Jobs\TenantAwareJob;
use App\Tenancy\TenantContext;
use Closure;

class SetTenantContextMiddleware
{
    public function handle(object $job, Closure $next): void
    {
        if ($job instanceof TenantAwareJob) {
            app()->instance(TenantContext::class, $job->getTenantContext());
        }

        $next($job);
    }
}
