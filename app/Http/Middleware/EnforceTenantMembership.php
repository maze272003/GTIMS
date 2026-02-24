<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces that the authenticated user has an active membership
 * for the resolved tenant context.
 */
class EnforceTenantMembership
{
    public function __construct(
        protected TenantResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        if (!$tenantContext) {
            abort(403, 'No tenant context available.');
        }

        $user = Auth::user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        if (!$this->resolver->userHasMembership($user, $tenantContext)) {
            abort(403, 'You do not have access to this tenant.');
        }

        return $next($request);
    }
}
