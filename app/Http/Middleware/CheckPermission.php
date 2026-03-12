<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Accepts one or more permission names (comma-separated in route definition).
     * The user passes if they have ANY of the listed permissions.
     *
     * Usage in routes:
     *   ->middleware('permission:inventory.view')
     *   ->middleware('permission:orders.view,orders.approve_admin')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!auth()->check()) {
            abort(403, 'This page cannot be accessed because your session is not authorized. Please sign in again.');
        }

        $user = auth()->user();

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'This page or action cannot be accessed with your account. Please contact the superadmin for assistance.');
    }
}
