<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

App::missing(function($exception){
    return Response::view('errors.404', [], 404);
});
App::error(function(Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
    return Response::view('errors.404', [], 404);
});
App::error(function(Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) {
    return Response::view('errors.not_allowed', [], 404);
});
App::error(function(Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    return Response::view('errors.404');
});

Route::get('/', function() {
    return View::make('index.index'); 
});

Route::group(['prefix' => 'v1'], function() {

    // Authentification 
    Route::post('auth',                 'AuthController@index');
    Route::post('auth/{device_id}',     'AuthController@update');
    
    // Categories
    Route::get('categories',            ['before' => 'auth', 'uses' => 'CategoriesController@index']);
    
    // Venues
    Route::get('venues',                ['before' => 'auth', 'uses' => 'VenuesController@index']);
    Route::get('venues/specials',       ['before' => 'auth', 'uses' => 'VenuesController@listSpecials']);
    Route::get('venues/favourites',     ['before' => 'auth', 'uses' => 'VenuesController@listFavourites']);
    Route::get('venues/suggestions',    ['before' => 'auth', 'uses' => 'VenuesController@showSuggestions']);
    Route::put('venues/{venue_id}/fav', ['before' => 'auth', 'uses' => 'VenuesController@markFavourite']);
    Route::get('venues/{venue_id}',     ['before' => 'auth', 'uses' => 'VenuesController@show']);

    // Users
    Route::post('users/email',          ['before' => 'auth', 'uses' => 'UsersController@register_email']);
    Route::post('users/token',          ['before' => 'auth', 'uses' => 'UsersController@register_token']);
    Route::post('users/push',           ['before' => 'auth', 'uses' => 'UsersController@send_push']);
    Route::get('users/push/reset',      ['before' => 'auth', 'uses' => 'UsersController@reset_pushes']);

    // FAQs
    Route::get('faqs',                  ['before' => 'auth', 'uses' => 'FaqController@index']);

    // Cities
    Route::get('cities',                ['before' => 'auth', 'uses' => 'CitiesController@index']);

    // Contents
    Route::get('content',               ['before' => 'auth', 'uses' => 'ContentsController@index']);
    Route::get('content/{id}',          ['before' => 'auth', 'uses' => 'ContentsController@details']);
});