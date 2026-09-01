<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function(){ return redirect('/admin'); });
Route::get('/home', function(){ return redirect('/admin'); });

/*
 * Punto de venta.
 *
 * Entrada separada del panel de administración: los cajeros llegan a /pos y no
 * pasan por Backpack. El blade solo monta el SPA; la autenticación ocurre
 * dentro, contra /pos-api, por JWT. El comodín deja que vue-router maneje sus
 * propias rutas sin que Laravel devuelva 404 al recargar una pantalla interna.
 */
Route::view('/pos/{cualquiera?}', 'pos')
    ->where('cualquiera', '.*')
    ->name('pos');

/*
 * Tienda en línea.
 *
 * Ruta pública pensada para enlazarse desde strangerthemes.com. El comodín deja que
 * vue-router maneje sus propias pantallas al recargar, igual que en el POS.
 */
Route::view('/comprar/{cualquiera?}', 'tienda')
    ->where('cualquiera', '.*')
    ->name('tienda');
