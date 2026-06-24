<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        Gate::define('customer', function (User $user) {
            return $user->role === 'customer';
        });

        Gate::define('store', function (User $user) {
            return $user->role === 'store';
        });

        Gate::define('admin', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('super-admin', function (User $user) {
            return $user->role === 'super-admin';
        });
    }
}

