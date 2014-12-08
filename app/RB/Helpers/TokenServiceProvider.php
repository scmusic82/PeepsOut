<?php namespace RB\Helpers;

use Illuminate\Support\ServiceProvider;

class TokenServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->bind('token', 'RB\Helpers\Token');
    }

}