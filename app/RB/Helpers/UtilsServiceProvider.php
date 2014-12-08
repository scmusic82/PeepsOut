<?php namespace RB\Helpers;

use Illuminate\Support\ServiceProvider;

class UtilsServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->bind('utils', 'RB\Helpers\Utils');
    }

}