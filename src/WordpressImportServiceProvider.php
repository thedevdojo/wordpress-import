<?php

namespace WordpressImport;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class WordpressImportServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wordpressimport');

        // Add routes behind Voyager authentication
        app(Dispatcher::class)->listen('voyager.admin.routing', function ($router) {
            $router->get('wordpress-import', function () {
                return view('wordpressimport::wpimport');
            });
            $router->post('wordpress-import', '\WordpressImport\Http\Controllers\ImportController@import');
        });
    }
}
