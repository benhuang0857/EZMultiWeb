<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/website', 'WebCTRLController@create');
Route::delete('/website', 'WebCTRLController@kill');
Route::get('/docker-status', 'WebCTRLController@get_docker_status');
Route::get('/container-status/{name}', 'WebCTRLController@get_container_status');
