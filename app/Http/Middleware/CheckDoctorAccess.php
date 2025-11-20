<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDoctorAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Dapat level 4 lang
        if (auth()->check() && auth()->user()->user_level_id == 4) {
            return $next($request);
        }
        abort(403, 'Access Denied. Doctors only.');
    }
}