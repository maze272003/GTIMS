<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use App\Models\User;
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
        // $this->registerPolicies();

        /**
         * Gate para sa mga feature na SUPERADMIN LANG ang pwedeng gumamit
         * (Tulad ng 'manage accounts')
         */
        Gate::define('be-superadmin', function (User $user) {
            // Check kung 'yung name sa level niya ay 'superadmin'
            return $user->level->name == 'superadmin';
        });

        /**
         * Gate para sa LAHAT ng pwedeng pumasok sa shared admin panel
         * (superadmin, admin, AT encoder)
         */
        Gate::define('can-access-admin-panel', function (User $user) {
            // Pwedeng pumasok basta 'superadmin', 'admin', O 'encoder'
            return in_array($user->level->name, [
                'superadmin', 
                'admin',
                'encoder'
            ]);
        });
        
        // (Wala na dito 'yung 'be-admin' at 'be-encoder' GATES
        // dahil pinalitan na natin ng 'can-access-admin-panel')
    }
}