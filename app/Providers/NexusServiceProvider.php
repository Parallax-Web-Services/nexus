<?php

namespace App\Providers;

use App\Support\Nexus;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class NexusServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('nexus', function ($app) {
            return new Nexus($app);
        });
    }
}
