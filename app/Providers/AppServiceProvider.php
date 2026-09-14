<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

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
        if (app()->bound('livewire')) {
            app('livewire')->addLocation(viewPath: resource_path('views/pages'));
            app('livewire')->addLocation(viewPath: resource_path('views/store'));
        }

        // Define rate limiter for login
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinutes(5,5)->by($request->ip());
        });

        // Use Bootstrap 5 styling for paginator (matches theme)
        Paginator::useBootstrapFive();
    }
}
