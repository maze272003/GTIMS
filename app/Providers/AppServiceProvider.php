<?php

namespace App\Providers;

// Idagdag mo itong dalawa:
use Illuminate\Support\Facades\Gate;
use App\Models\User;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // DITO MO ILAGAY 'YUNG BUONG CODE
        
        $this->registerPolicies(); // Gagana na 'to dito

        // I-define ang ating Gates
        
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