<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
{
    // Dapat level 1 O 2
    if (auth()->check() && in_array(auth()->user()->user_level_id, [1, 2])) {
        return $next($request);
    }
    abort(403, 'Access Denied. Admins and Superadmins only.');
}
}
