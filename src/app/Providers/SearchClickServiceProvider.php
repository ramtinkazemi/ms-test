<?php

namespace App\Providers;

use App\Services\Logger\SearchClickLog;
use Illuminate\Support\ServiceProvider;

class SearchClickServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->instance('SearchClickLog', new SearchClickLog());
    }
}
