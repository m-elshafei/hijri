<?php

namespace Elshafei\LaravelHijriDate;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class LaravelHijriDateServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        AliasLoader::getInstance()->alias('Hijri', Hijri::class);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('hijri', function () {
            return new Hijri();
        });
    }
}
