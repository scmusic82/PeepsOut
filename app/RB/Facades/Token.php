<?php namespace RB\Facades;

use Illuminate\Support\Facades\Facade;

class Token extends Facade {

    protected static function getFacadeAccessor()
    {
        return 'token';
    }

}