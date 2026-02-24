<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from route slugs ({provinceSlug}/{barangaySlug}).
 * Binds the resolved TenantContext to the request attributes.
 */
class ResolveTenantFromSlug
{
    public function __construct(
        protected TenantResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $provinceSlug = $request->route('provinceSlug');
        $barangaySlug = $request->route('barangaySlug');

        if (!$provinceSlug || !$barangaySlug) {
            abort(404, 'Tenant not found.');
        }

        $tenantContext = $this->resolver->fromSlugs($provinceSlug, $barangaySlug);

        if (!$tenantContext) {
            abort(404, 'Tenant not found.');
        }

        $request->attributes->set('tenantContext', $tenantContext);

        return $next($request);
    }
}
