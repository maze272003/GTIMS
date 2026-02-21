<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Respect forwarded headers from reverse proxies (Hostinger / Cloudflare / LB)
        // so Laravel can detect HTTPS correctly when the app server itself is on HTTP.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->web(append: [
            \App\Http\Middleware\RecordSystemActivityNotification::class,
        ]);

        $middleware->alias([
            'level.superadmin' => \App\Http\Middleware\CheckSuperAdminAccess::class,
            'level.admin'      => \App\Http\Middleware\CheckAdminAccess::class,
            'level.all'        => \App\Http\Middleware\CheckUserLevelAccess::class,
            'level.doctor'     => \App\Http\Middleware\CheckDoctorAccess::class,
            'level.mayor'      => \App\Http\Middleware\CheckMayorAccess::class,
            'level.finance'    => \App\Http\Middleware\CheckFinanceAccess::class,
            'permission'       => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
