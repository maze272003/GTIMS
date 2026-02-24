<?php

namespace App\Support;

use App\Tenancy\TenantContext;

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
        $ctx = $ctx ?? app(TenantContext::class);

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
