<?php

namespace App\Providers;

use App\Models\AuditEvent;
use App\Models\HistoryLog;
use App\Models\Hold;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patientrecords;
use App\Models\Supplier;
use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLoginFailed;
use App\Listeners\LogUserLogout;
use App\Policies\AuditEventPolicy;
use App\Policies\HistoryLogPolicy;
use App\Policies\HoldPolicy;
use App\Policies\IncomingRequestPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PatientRecordPolicy;
use App\Policies\SupplierPolicy;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $request = request();

        $shouldForceHttps = app()->environment('production')
            || $request->isSecure()
            || $request->header('X-Forwarded-Proto') === 'https'
            || str_starts_with((string) config('app.url'), 'https://');

        if ($shouldForceHttps) {
            URL::forceScheme('https');
        }
        View::composer('*', function ($view) {
            $view->with('currentAccessContext', app(\App\Services\CurrentAccessContextService::class)->build());
        });

        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(Patientrecords::class, PatientRecordPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(IncomingRequest::class, IncomingRequestPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Hold::class, HoldPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(HistoryLog::class, HistoryLogPolicy::class);

        /**
         * Gate para sa mga feature na SUPERADMIN LANG ang pwedeng gumamit
         * (Tulad ng 'manage accounts')
         */
        // Gate::define('be-superadmin', function (User $user) {
        //     // Check kung 'yung name sa level niya ay 'superadmin'
        //     return $user->level && $user->level->name == 'superadmin';
        // });
        // Gate::define('be-admin', function (User $user) {
        //     // Check kung 'yung name sa level niya ay 'admin'
        //     return $user->level && $user->level->name == 'admin';
        // });
        // Gate::define('be-encoder', function (User $user) {
        //     // Check kung 'yung name sa level niya ay 'encoder'
        //     return $user->level && $user->level->name == 'encoder';
        // });

        // /**
        //  * Gate para sa LAHAT ng pwedeng pumasok sa shared admin panel
        //  * (superadmin, admin, AT encoder)
        //  */
        // Gate::define('can-access-admin-panel', function (User $user) {
        //     // Pwedeng pumasok basta 'superadmin', 'admin', O 'encoder'
        //     return $user->level && in_array($user->level->name, [
        //         'superadmin', 
        //         'admin',
        //         'encoder'
        //     ]);
        // });
        
        // (Wala na dito 'yung 'be-admin' at 'be-encoder' GATES
        // dahil pinalitan na natin ng 'can-access-admin-panel')

        // Register Blade directive for permission-based rendering
        Blade::if('haspermission', function (string $permission) {
            $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;
            return auth()->check() && auth()->user()->hasPermission($permission, $tenantContext);
        });

        // Explicit event wiring for auth activity listeners.
        Event::listen(Login::class, LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);
        Event::listen(Failed::class, LogUserLoginFailed::class);

        RateLimiter::for('tenant-api', function (Request $request) {
            $province = (string) ($request->route('provinceSlug') ?? session('tenant.route_slug_province') ?? 'platform');
            $barangay = (string) ($request->route('barangaySlug') ?? session('tenant.route_slug_barangay') ?? 'all');
            $tenantKey = "{$province}:{$barangay}";
            $limit = (int) config('tenancy.rate_limits.tenant_api_per_minute', 100);

            return Limit::perMinute($limit)->by($tenantKey . ':' . $request->ip());
        });

        RateLimiter::for('tenant-login', function (Request $request) {
            $province = (string) ($request->route('provinceSlug') ?? $request->input('provinceSlug') ?? 'unknown');
            $barangay = (string) ($request->route('barangaySlug') ?? $request->input('barangaySlug') ?? 'unknown');
            $tenantKey = "{$province}/{$barangay}";
            $limit = (int) config('tenancy.rate_limits.tenant_login_per_minute', 5);

            return Limit::perMinute($limit)->by($tenantKey . ':' . $request->ip());
        });

        RateLimiter::for('moderator-login', function (Request $request) {
            $limit = (int) config('tenancy.rate_limits.moderator_login_per_minute', 10);
            return Limit::perMinute($limit)->by('moderator:' . $request->ip() . ':' . (string) $request->input('email'));
        });

        RateLimiter::for('tenant-export', function (Request $request) {
            $province = (string) ($request->route('provinceSlug') ?? session('tenant.route_slug_province') ?? 'platform');
            $barangay = (string) ($request->route('barangaySlug') ?? session('tenant.route_slug_barangay') ?? 'all');
            $tenantKey = "{$province}:{$barangay}";
            $limit = (int) config('tenancy.rate_limits.tenant_export_per_hour', 10);
            $userKey = $request->user()?->id ?? $request->ip();

            return Limit::perHour($limit)->by($tenantKey . ':' . $userKey);
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $provinceSlug = session('tenant.route_slug_province') ?? request()->route('provinceSlug');
            $barangaySlug = session('tenant.route_slug_barangay') ?? request()->route('barangaySlug');

            if ($provinceSlug && $barangaySlug && Route::has('tenant.password.reset')) {
                return route('tenant.password.reset', [
                    'provinceSlug' => $provinceSlug,
                    'barangaySlug' => $barangaySlug,
                    'token' => $token,
                    'email' => $user->email,
                ]);
            }

            if (request()->routeIs('moderator.*') && Route::has('moderator.password.reset')) {
                return route('moderator.password.reset', [
                    'token' => $token,
                    'email' => $user->email,
                ]);
            }

            return route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            $provinceSlug = session('tenant.route_slug_province') ?? request()->route('provinceSlug');
            $barangaySlug = session('tenant.route_slug_barangay') ?? request()->route('barangaySlug');
            $expiresAt = now()->addMinutes(config('auth.verification.expire', 60));

            $params = [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ];

            if ($provinceSlug && $barangaySlug && Route::has('tenant.verification.verify')) {
                return URL::temporarySignedRoute(
                    'tenant.verification.verify',
                    $expiresAt,
                    array_merge($params, [
                        'provinceSlug' => $provinceSlug,
                        'barangaySlug' => $barangaySlug,
                    ])
                );
            }

            if (request()->routeIs('moderator.*') && Route::has('moderator.verification.verify')) {
                return URL::temporarySignedRoute('moderator.verification.verify', $expiresAt, $params);
            }

            return URL::temporarySignedRoute('verification.verify', $expiresAt, $params);
        });
    }
}
