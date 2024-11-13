<?php
use Illuminate\Support\Facades\Route;
Route::get('sucursal',[\App\Http\Controllers\Admin\SucursalCrudController::class,'fetch']);
Route::get('users',[\App\Http\Controllers\Admin\UserController::class,'fetch']);
Route::get('pagos/concepto',[\App\Http\Controllers\Admin\PagoConceptoCrudController::class,'fetch']);
Route::get('ventas/resumen',[\App\Http\Controllers\Admin\VentaCrudController::class,'resumen']);
Route::get('ventas/resumen/productos',[\App\Http\Controllers\Admin\VentaCrudController::class,'resumenProductos']);
Route::get('ventas/resumen/productos/descuentos',[\App\Http\Controllers\Admin\VentaCrudController::class,'resumenProductosDescuentos']);
