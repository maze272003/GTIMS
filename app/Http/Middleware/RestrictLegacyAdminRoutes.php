<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictLegacyAdminRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('tenancy.legacy_admin.enabled', true)) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $moderatorOnly = (bool) config('tenancy.legacy_admin.moderator_only', true);
        if (!$moderatorOnly || $user->isModerator()) {
            return $next($request);
        }

        $provinceSlug = session('tenant.route_slug_province');
        $barangaySlug = session('tenant.route_slug_barangay');
        if ($provinceSlug && $barangaySlug && $request->routeIs('admin.*')) {
            return redirect()->route('tenant.dashboard', [
                'provinceSlug' => $provinceSlug,
                'barangaySlug' => $barangaySlug,
            ]);
        }

        abort(403, 'Legacy admin routes are disabled for tenant users.');
    }
}

