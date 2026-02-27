<?php

namespace App\Jobs\Middleware;

use App\Jobs\TenantAwareJob;
use App\Tenancy\TenantContext;
use Closure;
use RuntimeException;
use Illuminate\Support\Facades\Log;

class SetTenantContextMiddleware
{
    public function handle(object $job, Closure $next): void
    {
        if ($job instanceof TenantAwareJob) {
            $tenantContext = $job->getTenantContext();

            if (!$tenantContext) {
                Log::error('Tenant-aware job failed: missing or invalid tenant context', [
                    'job' => $job::class,
                ]);

                if (method_exists($job, 'fail')) {
                    $job->fail(new RuntimeException('Missing or invalid tenant context for queued job.'));
                    return;
                }

                throw new RuntimeException('Missing or invalid tenant context for queued job.');
            }

            app()->instance(TenantContext::class, $tenantContext);
            Log::withContext([
                'tenant_scope' => $tenantContext->scopeType,
                'tenant_province_id' => $tenantContext->provinceId,
                'tenant_barangay_id' => $tenantContext->barangayId,
                'tenant_province_slug' => $tenantContext->provinceSlug,
                'tenant_barangay_slug' => $tenantContext->barangaySlug,
                'job' => $job::class,
            ]);
        }

        $next($job);
    }
}
