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
        $shouldForceHttps = app()->environment('production')
            || request()->isSecure()
            || request()->header('X-Forwarded-Proto') === 'https'
            || str_starts_with((string) config('app.url'), 'https://');

        if ($shouldForceHttps) {
            URL::forceScheme('https');
        }

        $this->setupQueryMonitoring();
        // $this->registerPolicies();

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
            return auth()->check() && auth()->user()->hasPermission($permission);
        });

        Blade::if('hasanypermission', function (...$permissions) {
            if (! auth()->check()) {
                return false;
            }

            return (new PermissionView(auth()->user(), app(AuthSessionService::class)))->hasAny($permissions);
        });

        Blade::if('hasallpermissions', function (...$permissions) {
            if (! auth()->check()) {
                return false;
            }

            return (new PermissionView(auth()->user(), app(AuthSessionService::class)))->hasAll($permissions);
        });

        View::composer('*', function ($view) {
            $view->with('permissionView', new PermissionView(auth()->user(), app(AuthSessionService::class)));
        });

        // Register the Workflow policy
        Gate::policy(WorkflowDefinition::class, WorkflowDefinitionPolicy::class);

        // Register model observers for workflow event-driven triggers
        Inventory::observe(InventoryWorkflowObserver::class);
        Order::observe(OrderWorkflowObserver::class);

        // Auth listeners are auto-discovered from app/Listeners.
    }

    private function setupQueryMonitoring(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $context = [
                'time_ms' => round($query->time, 2),
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'connection' => $query->connectionName,
                'route' => request()?->route()?->getName(),
                'url' => app()->runningInConsole() ? null : request()?->fullUrl(),
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
