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
    Route::get('auth', function(){ return ''; });
    Route::post('auth',                 'AuthController@index');
    Route::post('auth/{device_id}',     'AuthController@update');
    Route::delete('auth/{device_id}',   'AuthController@destroy');
    
    // Categories
    Route::get('categories',            'CategoriesController@index');
    
    // Venues
    Route::get('venues',                'VenuesController@index');
    Route::get('venues/specials',       'VenuesController@listSpecials');
    Route::get('venues/favourites',     'VenuesController@listFavourites');
    Route::put('venues/{venue_id}/fav', 'VenuesController@markFavourite');
    Route::get('venues/{venue_id}',     'VenuesController@show');

    // Users
    Route::get('users/{user_id}',       'UsersController@show');

    // FAQs
    Route::get('faqs',                  'FaqController@index');

    // Cities
    Route::get('cities',                'CitiesController@index');
});