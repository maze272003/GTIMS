<?php

namespace App\Providers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\WorkflowDefinition;
use App\Observers\InventoryWorkflowObserver;
use App\Observers\OrderWorkflowObserver;
use App\Policies\WorkflowDefinitionPolicy;
use App\Services\AuthSessionService;
use App\Support\PermissionView;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
        $this->registerOctaneSafeHttpsRedirect();

        $this->setupQueryMonitoring();

        $this->registerBladeDirectives();

        $this->registerViewComposers();

        $this->registerPolicies();

        $this->registerModelObservers();
    }

    private function registerOctaneSafeHttpsRedirect(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $shouldForceHttps = $this->app->environment('production')
            || ($this->app['request'] && $this->app['request']->isSecure())
            || ($this->app['request'] && $this->app['request']->header('X-Forwarded-Proto') === 'https')
            || str_starts_with((string) config('app.url'), 'https://');

        if ($shouldForceHttps) {
            URL::forceScheme('https');
        }
    }

    private function registerBladeDirectives(): void
    {
        Blade::if('haspermission', function (string $permission) {
            if (! auth()->check()) {
                return false;
            }

            return auth()->user()->hasPermission($permission);
        });

        Blade::if('hasanypermission', function (...$permissions) {
            if (! auth()->check()) {
                return false;
            }

            $authSessionService = app(AuthSessionService::class);
            return (new PermissionView(auth()->user(), $authSessionService))->hasAny($permissions);
        });

        Blade::if('hasallpermissions', function (...$permissions) {
            if (! auth()->check()) {
                return false;
            }

            $authSessionService = app(AuthSessionService::class);
            return (new PermissionView(auth()->user(), $authSessionService))->hasAll($permissions);
        });
    }

    private function registerViewComposers(): void
    {
        View::composer('*', function ($view) {
            if (! auth()->check()) {
                return;
            }

            $authSessionService = app(AuthSessionService::class);
            $view->with('permissionView', new PermissionView(auth()->user(), $authSessionService));
        });
    }

    private function registerPolicies(): void
    {
        Gate::policy(WorkflowDefinition::class, WorkflowDefinitionPolicy::class);
    }

    private function registerModelObservers(): void
    {
        Inventory::observe(InventoryWorkflowObserver::class);
        Order::observe(OrderWorkflowObserver::class);
    }

    private function setupQueryMonitoring(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                return;
            }

            $context = [
                'time_ms' => round($query->time, 2),
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'connection' => $query->connectionName,
                'route' => $this->app['request']?->route()?->getName(),
                'url' => $this->app['request']?->fullUrl(),
            ];

            $warningThreshold = (int) config('database.slow_query_warning_ms', 500);
            $errorThreshold = (int) config('database.slow_query_error_ms', 2000);

            if ($query->time > $errorThreshold) {
                Log::error('Very slow query detected.', $context);
            } elseif ($query->time > $warningThreshold) {
                Log::warning('Slow query detected.', $context);
            }

            if (config('database.log_all_queries', false)) {
                Log::debug('Query executed.', $context);
            }
        });
    }
}
