<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDoctorAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->hasPermission('patients.view')) {
            return $next($request);
        }
        abort(403, 'Access Denied. You do not have the required permission.');
    }
}