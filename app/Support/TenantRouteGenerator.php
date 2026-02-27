<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use RuntimeException;

class TenantRouteGenerator
{
    /**
     * Generate a tenant-scoped route URL.
     *
     * @param string $name Route name (e.g. 'tenant.dashboard')
     * @param array $params Additional route parameters
     * @param TenantContext|null $ctx Optional tenant context (uses current if null)
     */
    public static function tenantRoute(string $name, array $params = [], ?TenantContext $ctx = null): string
    {
        if (!$ctx && app()->bound(TenantContext::class)) {
            $ctx = app(TenantContext::class);
        }

        if (!$ctx) {
            throw new RuntimeException('Tenant context is required to generate tenant routes.');
        }

        $params = array_merge([
            'provinceSlug' => $ctx->provinceSlug,
            'barangaySlug' => $ctx->barangaySlug,
        ], $params);

        return route($name, $params);
    }

    /**
     * Generate a moderator route URL.
     */
    public static function moderatorRoute(string $name, array $params = []): string
    {
        return route($name, $params);
    }
}
