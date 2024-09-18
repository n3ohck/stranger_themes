<?php
use Illuminate\Support\Facades\Route;
Route::get('capacitacion/tipos',[\App\Http\Controllers\Admin\CapacitacionTipoCrudController::class,'fetch']);
Route::get('capacitacion/tipo/subtipo',[\App\Http\Controllers\Admin\CapacitacionTipoSubtipoCrudController::class,'fetch']);
