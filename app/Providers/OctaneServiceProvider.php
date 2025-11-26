<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class OctaneServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set PHP configuration for file uploads at runtime
        // This fixes FrankenPHP worker mode not reading php.ini
        if (config('octane.server') === 'frankenphp') {
            ini_set('upload_max_filesize', '25M');
            ini_set('post_max_size', '30M');
            ini_set('max_execution_time', '300');
            ini_set('max_input_time', '300');
            ini_set('memory_limit', '512M');
        }
    }
}