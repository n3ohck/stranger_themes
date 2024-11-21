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
Route::get('reservas', [\App\Http\Controllers\Admin\ReservaCrudController::class,'fetch']);
Route::group(['middleware' => ['jwt.verify']], function () {
    //Login
    Route::post('user', 'App\Http\Controllers\Admin\UserController@getAuthenticatedUser');
    Route::get('user/sucursal',['App\Http\Controllers\Admin\SucursalCrudController','get']);

    //Productos
    Route::get('productos', [\App\Http\Controllers\Admin\ProductoCrudController::class,'fetch']);

    //Ventas
    Route::post('ventas/make', [\App\Http\Controllers\Admin\VentaCrudController::class,'make']);
    Route::post('ventas/cancel', [\App\Http\Controllers\Admin\VentaCrudController::class,'cancel']);
    Route::get('ventas', [\App\Http\Controllers\Admin\VentaCrudController::class,'fetch']);

    //Descuentos
    Route::get('descuentos', [\App\Http\Controllers\Admin\DescuentoCrudController::class,'fetch']);

    //Reservas
    //Route::get('reservas', [\App\Http\Controllers\Admin\ReservaCrudController::class,'fetch']);
    Route::post('reservas', [\App\Http\Controllers\Admin\ReservaCrudController::class,'createReserva']);

    //Aperturas
    Route::post('apertura', [\App\Http\Controllers\Admin\AperturaCrudController::class,'make']);
    Route::get('apertura', [\App\Http\Controllers\Admin\AperturaCrudController::class,'fetch']);

    //Cortes
    Route::post('corte', [\App\Http\Controllers\Admin\CorteCrudController::class,'make']);
    Route::get('corte', [\App\Http\Controllers\Admin\CorteCrudController::class,'fetch']);

    //Egresos
    Route::post('egreso', [\App\Http\Controllers\Admin\EgresoCrudController::class,'make']);
    Route::get('egreso', [\App\Http\Controllers\Admin\EgresoCrudController::class,'fetch']);

    //Empleados
    Route::get('empleados', [\App\Http\Controllers\Admin\EmpleadoCrudController::class,'fetch']);
    Route::post('empleados/pago', [\App\Http\Controllers\Admin\EmpleadoPagoCrudController::class,'make']);
    Route::get('empleados/pago', [\App\Http\Controllers\Admin\EmpleadoPagoCrudController::class,'fetch']);

});
