<?php

namespace App\Providers;

use App\Services\Logger\SearchClickLog;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
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
        //$this->app->bind(\App\Repositories\EsSearchRepositoryInterface::class, \App\Repositories\SearchRepositoryV2::class);
        $this->app->bind(\App\Repositories\EsSearchRepositoryInterface::class, \App\Repositories\SearchRepository::class);
    }
}
