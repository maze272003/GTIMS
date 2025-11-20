<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;

use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLoginFailed;

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
         $hosting = env('APP_HOSTING', 'local'); // default to 'local'

        // Detect Cloudflare tunnel / proxy (X-Forwarded-Proto)
        if ($hosting === 'cloudflare') {
            if (request()->header('X-Forwarded-Proto') === 'https') {
                URL::forceScheme('https');
            }
        }

        // Detect Hostinger environment (direct HTTPS)
        elseif ($hosting === 'hostinger') {
            if (request()->isSecure()) {
                URL::forceScheme('https');
            }
        }

        // (Optional) Always force HTTPS in production
        elseif (app()->environment('production')) {
            URL::forceScheme('https');
        }
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
    }
}