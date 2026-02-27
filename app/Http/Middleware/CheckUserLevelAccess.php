<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserLevelAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            abort(403, 'Access Denied. You do not have permission.');
        }

        $user = auth()->user();
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        $hasScopedRole = $user->roleAssignments()->exists() || $user->isModerator();
        $hasLegacyLevel = !is_null($user->user_level_id);
        $hasMembership = $tenantContext ? $user->hasActiveMembership($tenantContext) : true;

        if (($hasScopedRole || $hasLegacyLevel) && $hasMembership) {
            return $next($request);
        }

        abort(403, 'Access Denied. You do not have permission.');
    }
}
