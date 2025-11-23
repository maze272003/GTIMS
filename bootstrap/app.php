<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'level.superadmin' => \App\Http\Middleware\CheckSuperAdminAccess::class,
            'level.admin'      => \App\Http\Middleware\CheckAdminAccess::class,
            'level.all'        => \App\Http\Middleware\CheckUserLevelAccess::class,
            'level.doctor'     => \App\Http\Middleware\CheckDoctorAccess::class,
            'level.mayor'      => \App\Http\Middleware\CheckMayorAccess::class,
            'level.finance'    => \App\Http\Middleware\CheckFinanceAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
