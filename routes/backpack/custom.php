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
    Route::get('dashboard', [\App\Http\Controllers\Admin\DashboardCrudController::class, 'dashboard']);

    Route::crud('user', 'UserController');
    Route::crud('slider', 'SliderCrudController');
    Route::crud('formato-descargable', 'FormatoDescargableCrudController');
    Route::crud('formato-descargable-categoria', 'FormatoDescargableCategoriaCrudController');
    Route::crud('calendario', 'CalendarioCrudController');
    Route::crud('calendario-categoria', 'CalendarioCategoriaCrudController');
    Route::crud('directorio-departamento', 'DirectorioDepartamentoCrudController');
    Route::crud('directorio-puesto', 'DirectorioPuestoCrudController');
    Route::crud('directorio-proyecto', 'DirectorioProyectoCrudController');
    Route::crud('directorio-categorias', 'DirectorioCategoriasCrudController');
    Route::crud('directorio-personas', 'DirectorioPersonasCrudController');
    Route::crud('revista', 'RevistaCrudController');
    Route::crud('ubicacion-proyecto', 'UbicacionProyectoCrudController');
    Route::crud('convenio', 'ConvenioCrudController');
    Route::crud('tipo-convenio', 'TipoConvenioCrudController');
    Route::crud('queja-sugerencia', 'QuejaSugerenciaCrudController');
    Route::crud('contacto-comunicacion', 'ContactoComunicacionCrudController');
    Route::crud('capacitacion-tipo', 'CapacitacionTipoCrudController');
    Route::crud('capacitacion', 'CapacitacionCrudController');
    Route::crud('capacitacion-file', 'CapacitacionFileCrudController');
    Route::crud('titulo', 'TituloCrudController');
    Route::crud('calendario-mensual', 'CalendarioMensualCrudController');
    Route::crud('calendario-anual', 'CalendarioAnualCrudController');
    Route::crud('capacitacion-tipo-subtipo', 'CapacitacionTipoSubtipoCrudController');
}); // this should be the absolute last line of this file
