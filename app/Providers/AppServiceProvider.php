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
        // $this->registerPolicies(); // <-- BURAHIN MO ITONG LINE NA 'TO

        // I-define ang ating Gates (Gagana 'to kahit wala 'yung line sa taas)
        
        Gate::define('be-superadmin', function (User $user) {
            // Check kung 'yung name sa level niya ay 'superadmin'
            return $user->level->name == 'superadmin';
        });

        Gate::define('be-admin', function (User $user) {
            // Pwedeng pumasok basta 'superadmin' O 'admin'
            return in_array($user->level->name, ['superadmin', 'admin']);
        });
        
        // Pwede ka ring gumawa para sa encoder
        Gate::define('be-encoder', function (User $user) {
            return $user->level->name == 'encoder';
        });
    }
}