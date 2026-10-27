<?php

use App\Http\Controllers\Tienda\CatalogoController;
use App\Http\Controllers\Tienda\CheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de la tienda en línea
|--------------------------------------------------------------------------
|
| Prefijo /tienda-api. Público y sin sesión: lo consume la pantalla de compra
| que se enlaza desde strangerthemes.com.
|
| El límite de peticiones está puesto porque son rutas abiertas a internet:
| el catálogo se consulta seguido y se deja holgado, pero crear intentos de
| pago aparta horarios, así que ahí se limita fuerte. Cada ruta tiene su límite
| con nombre (RouteServiceProvider::configureRateLimiting) para que no se gasten
| el cupo unas a otras.
|
*/

Route::get('catalogo', [CatalogoController::class, 'index'])
    ->middleware('throttle:tienda-catalogo')
    ->name('catalogo');

Route::get('disponibilidad', [CatalogoController::class, 'disponibilidad'])
    ->middleware('throttle:tienda-disponibilidad')
    ->name('disponibilidad');

Route::get('descuento', [CatalogoController::class, 'descuento'])
    ->middleware('throttle:tienda-descuento')
    ->name('descuento');

Route::post('checkout', [CheckoutController::class, 'crear'])
    ->middleware('throttle:tienda-checkout')
    ->name('checkout');

Route::get('comprobante/{referencia}', [CheckoutController::class, 'comprobante'])
    ->middleware('throttle:tienda-comprobante')
    ->name('comprobante');

// Lo llama Stripe, no el navegador: sin límite de peticiones para no perder avisos
// de pago, y la autenticidad la da la firma del propio webhook.
Route::post('webhook/stripe', [CheckoutController::class, 'webhook'])->name('webhook');
