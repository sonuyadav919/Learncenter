<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/migrate', function () {
    Artisan::call('migrate');
});

Route::get('/', function () {
    return view('welcome', ['user' => Auth::user()]);
});

Route::auth();

Route::get('/home', 'HomeController@index');


Route::get('/register', 'HomeController@create');
Route::post('/reg', 'HomeController@store');

Route::auth();

Route::get('/home', 'HomeController@index');

Route::controller('/dashboard', 'DashboardController');
Route::controller('/profile', 'ProfileController');
Route::get('/notes/searchfile', 'NotesController@getSearchfile');
Route::get('/notes/{fileid}', 'NotesController@getIndex');
Route::controller('/notes', 'NotesController');



Route::group(['middleware' => 'auth'], function()
{
	Route::get('applogs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
});
