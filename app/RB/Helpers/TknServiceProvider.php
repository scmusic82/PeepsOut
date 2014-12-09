<?php namespace RB\Helpers;

use \Illuminate\Support\ServiceProvider;

class TknServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->bind('tkn', 'RB\Helpers\Tkn');
    }

}