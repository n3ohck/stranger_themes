<?php
use Illuminate\Support\Facades\Route;
Route::get('sucursal',[\App\Http\Controllers\Admin\SucursalCrudController::class,'fetch']);
