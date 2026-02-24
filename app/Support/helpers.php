<?php

use App\Support\TenantRouteGenerator;
use App\Tenancy\TenantContext;

if (!function_exists('tenant_route')) {
    /**
     * Generate a tenant-scoped route URL.
     */
    function tenant_route(string $name, array $params = [], ?TenantContext $ctx = null): string
    {
        return TenantRouteGenerator::tenantRoute($name, $params, $ctx);
    }
}

if (!function_exists('moderator_route')) {
    /**
     * Generate a moderator route URL.
     */
    function moderator_route(string $name, array $params = []): string
    {
        return TenantRouteGenerator::moderatorRoute($name, $params);
    }
}

if (!function_exists('current_tenant')) {
    /**
     * Get the current tenant context, or null if not in a tenant scope.
     */
    function current_tenant(): ?TenantContext
    {
        try {
            return app(TenantContext::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
