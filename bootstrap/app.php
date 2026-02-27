<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Tenancy\TenantContext;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require_once __DIR__ . '/../app/Support/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/tenant.php'));
        },
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
            \App\Http\Middleware\ApplyTenantLogContext::class,
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
            'moderator.only'   => \App\Http\Middleware\CheckModeratorAccess::class,
            'legacy.admin'     => \App\Http\Middleware\RestrictLegacyAdminRoutes::class,
            'tenant.resolve'   => \App\Http\Middleware\ResolveTenantFromSlug::class,
            'tenant.membership' => \App\Http\Middleware\EnforceTenantMembership::class,
            'tenant.bind'      => \App\Http\Middleware\BindTenantContext::class,
            'tenant.modelscope' => \App\Http\Middleware\EnforceTenantModelScope::class,
            'tenant.foreign_keys' => \App\Http\Middleware\ValidateTenantForeignKeys::class,
            'tenant.api.auth' => \App\Http\Middleware\AuthenticateTenantApiToken::class,
            'tenant.api.match' => \App\Http\Middleware\EnsureApiTenantMatchesToken::class,
            'tenant.api.ability' => \App\Http\Middleware\RequireTenantApiAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception) {
            $tenantContext = app()->bound(TenantContext::class)
                ? app(TenantContext::class)
                : TenantContext::fromSession([
                    'tenant.scope_type' => session('tenant.scope_type'),
                    'tenant.province_id' => session('tenant.province_id'),
                    'tenant.barangay_id' => session('tenant.barangay_id'),
                    'tenant.route_slug_province' => session('tenant.route_slug_province'),
                    'tenant.route_slug_barangay' => session('tenant.route_slug_barangay'),
                ]);

            if ($tenantContext) {
                Log::withContext([
                    'tenant_scope' => $tenantContext->scopeType,
                    'tenant_province_id' => $tenantContext->provinceId,
                    'tenant_barangay_id' => $tenantContext->barangayId,
                    'tenant_province_slug' => $tenantContext->provinceSlug,
                    'tenant_barangay_slug' => $tenantContext->barangaySlug,
                ]);
            }

            if (str_contains(strtolower($exception->getMessage()), 'cross-tenant')) {
                Log::channel('security')->critical('Cross-tenant access attempt detected', [
                    'message' => $exception->getMessage(),
                    'user_id' => auth()->id(),
                ]);
            }
        });

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
    })->create();
