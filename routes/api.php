<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::post('register', 'App\Http\Controllers\AuthController@register');
Route::post('login', 'App\Http\Controllers\AuthController@authenticate');
Route::group(['middleware' => ['jwt.verify']], function () {
    Route::post('user', 'App\Http\Controllers\Admin\UserController@getAuthenticatedUser');
    Route::get('productos', [\App\Http\Controllers\Admin\ProductoCrudController::class,'fetch']);
    Route::get('descuentos', [\App\Http\Controllers\Admin\DescuentoCrudController::class,'fetch']);
    Route::post('ventas/make', [\App\Http\Controllers\Admin\VentaCrudController::class,'make']);
    Route::post('ventas/cancel', [\App\Http\Controllers\Admin\VentaCrudController::class,'cancel']);
    Route::get('reservas', [\App\Http\Controllers\Admin\ReservaCrudController::class,'fetch']);
    Route::post('reservas', [\App\Http\Controllers\Admin\ReservaCrudController::class,'createReserva']);
});
