<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the resolved TenantContext into the application container,
 * session, and view shared data so it is accessible everywhere.
 */
class BindTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        if ($tenantContext) {
            // Bind as singleton so services can inject it
            app()->instance(TenantContext::class, $tenantContext);

            // Store in session for subsequent requests
            foreach ($tenantContext->toSessionData() as $key => $value) {
                session([$key => $value]);
            }

            // Share with all views
            View::share('tenantContext', $tenantContext);
        }

        return $next($request);
    }
}
