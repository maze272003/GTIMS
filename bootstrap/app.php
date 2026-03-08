<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            \App\Http\Middleware\SanitizeInput::class,
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
        $render403 = static function ($exception) {
            return response()->view('errors.403', [
                'exception' => $exception,
            ], 403);
        };

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($render403) {
            return $render403($exception);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($render403) {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            return $render403($exception);
        });

        $exceptions->report(function (\Throwable $e) {
            Log::error('Unhandled exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });
    })->create();
