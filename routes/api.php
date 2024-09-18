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
Route::get('directorio/personas', [\App\Http\Controllers\Admin\DirectorioPersonasCrudController::class, 'get']);
Route::get('titulos', [\App\Http\Controllers\Admin\TituloCrudController::class, 'get']);
Route::group(['middleware' => ['jwt.verify']], function () {
    Route::post('user', 'App\Http\Controllers\Admin\UserController@getAuthenticatedUser');
    Route::get('sliders', [\App\Http\Controllers\Admin\SliderCrudController::class, 'get']);
    Route::get('formatos_descargables_categorias', [\App\Http\Controllers\Admin\FormatoDescargableCategoriaCrudController::class, 'get']);
    Route::get('formatos_descargables', [\App\Http\Controllers\Admin\FormatoDescargableCrudController::class, 'get']);
    Route::get('directorio_categorias', [\App\Http\Controllers\Admin\DirectorioCategoriasCrudController::class, 'get']);
    Route::get('directorio/proyectos', [\App\Http\Controllers\Admin\DirectorioProyectoCrudController::class, 'get']);
    Route::get('directorio/puestos', [\App\Http\Controllers\Admin\DirectorioPuestoCrudController::class, 'get']);
    Route::get('directorio/departamentos', [\App\Http\Controllers\Admin\DirectorioDepartamentoCrudController::class, 'get']);

    Route::get('proyectos/ubicaciones', [\App\Http\Controllers\Admin\UbicacionProyectoCrudController::class, 'get']);
    Route::get('convenios/categorias', [\App\Http\Controllers\Admin\TipoConvenioCrudController::class, 'get']);
    Route::get('convenios', [\App\Http\Controllers\Admin\ConvenioCrudController::class, 'get']);
    Route::put('quejas_sugerencias', [\App\Http\Controllers\Admin\QuejaSugerenciaCrudController::class, 'put']);
    Route::put('contacto-comunicacion', [\App\Http\Controllers\Admin\ContactoComunicacionCrudController::class, 'put']);
    Route::get('calendario/categorias', [\App\Http\Controllers\Admin\CalendarioCategoriaCrudController::class, 'get']);
    Route::get('calendario', [\App\Http\Controllers\Admin\CalendarioCrudController::class, 'get']);
    Route::get('revistas', [\App\Http\Controllers\Admin\RevistaCrudController::class, 'get']);
    Route::get('capacitaciones/tipos', [\App\Http\Controllers\Admin\CapacitacionTipoCrudController::class, 'get']);
    Route::get('capacitaciones/tipos/{capacitacion_tipo}', [\App\Http\Controllers\Admin\CapacitacionTipoCrudController::class, 'getTipo']);
    Route::get('capacitaciones/subtipos/{capacitacion_subtipo}', [\App\Http\Controllers\Admin\CapacitacionTipoSubtipoCrudController::class, 'getSubtipo']);
    Route::get('capacitaciones', [\App\Http\Controllers\Admin\CapacitacionCrudController::class, 'get']);
    Route::get('capacitaciones/{capacitacion}', [\App\Http\Controllers\Admin\CapacitacionCrudController::class, 'getCapacitacion']);
    Route::post('user/change_profile_image', [\App\Http\Controllers\Admin\UserController::class, 'changeProfileImage']);
    Route::get('calendario/archivos/mensual', [\App\Http\Controllers\Admin\CalendarioMensualCrudController::class, 'get']);
    Route::get('calendario/archivos/anual', [\App\Http\Controllers\Admin\CalendarioAnualCrudController::class, 'get']);
    Route::get('capacitacion/comentarios', [\App\Http\Controllers\Admin\CapacitacionCrudController::class, 'getCommentsWithReplies']);
    Route::post('capacitacion/comentario', [\App\Http\Controllers\Admin\CapacitacionCrudController::class, 'storeComment']);
});
