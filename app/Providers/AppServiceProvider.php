<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Load Pail hanya di environment local/development
        if ($this->app->environment('local', 'development')) {
            if (class_exists(\Laravel\Pail\PailServiceProvider::class)) {
                $this->app->register(\Laravel\Pail\PailServiceProvider::class);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
