<?php namespace AltTabMobile\Utils;

use Illuminate\Support\ServiceProvider;

class UtilsServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->bind('utils', 'AltTabMobile\Utils\Utils');
    }

}