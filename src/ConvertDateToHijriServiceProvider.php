<?php

namespace MoustafaElshafei\ConvertDateToHijriServiceProvider;

use Illuminate\Support\ServiceProvider;
use MoustafaElshafei\ConvertDateToHijri\ConvertDateToHijri;

class ConvertDateToHijriServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'convertDateToHijri');

        // Optionally, publish the views to the main application's resources/views/vendor directory
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/convertDateToHijri'),
        ], 'views');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // ...
    }
}
