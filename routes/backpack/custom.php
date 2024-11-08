<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\Base.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace'  => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('user', 'UserController');
    Route::crud('sucursal', 'SucursalCrudController');
    Route::crud('producto', 'ProductoCrudController');
    Route::crud('descuento', 'DescuentoCrudController');
    Route::crud('venta', 'VentaCrudController');
    Route::crud('reserva', 'ReservaCrudController');
    Route::crud('configuracion', 'ConfiguracionCrudController');
    Route::crud('pago-concepto', 'PagoConceptoCrudController');
    Route::crud('pago-carta', 'PagoCartaCrudController');
    Route::get('pago-carta/{pagoCarta}/pdf', 'PagoCartaCrudController@pdf')->name('pago-carta.pdf');
    Route::crud('egreso', 'EgresoCrudController');
    Route::crud('apertura', 'AperturaCrudController');
    Route::crud('corte', 'CorteCrudController');
    Route::crud('empleado', 'EmpleadoCrudController');
    Route::crud('empleado-pago', 'EmpleadoPagoCrudController');
    Route::get('dashboard', 'DashboardController@index');
}); // this should be the absolute last line of this file
