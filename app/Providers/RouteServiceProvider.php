<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware(array_merge(
                (array)config('backpack.base.web_middleware', 'web'),
                (array)config('backpack.base.middleware_key', 'admin')
            ))
                ->prefix('webapi')
                ->namespace('App\Http\Controllers\Admin')
                ->group(base_path('routes/webapi.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::prefix('pos-api')
                ->middleware('api')
                ->as('pos.')
                ->group(base_path('routes/pos.php'));

            Route::prefix('tienda-api')
                ->middleware(['api', 'tienda.seguridad'])
                ->as('tienda.')
                ->group(base_path('routes/tienda.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Por usuario o por IP. Sin by() la cuenta era una sola para todo el
        // sistema: 60 peticiones por minuto entre todos los cajeros, la tienda y la
        // API juntos. Este límite corre antes que jwt.verify, así que en la práctica
        // cuenta por IP, y los cajeros de una sucursal comparten la suya: por eso
        // 120 y no 60 (el POS consulta horarios y fechas por cada recorrido).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(optional($request->user())->id ?: $request->ip());
        });

        // Tienda en línea: un límite con nombre por ruta. Con throttle:N,M todas
        // las rutas compartían la misma cuenta por IP, y consultar horarios gastaba
        // el cupo de pagar: tras diez cambios de fecha, Pagar respondía 429.
        foreach ([
            'tienda-catalogo' => 60,
            'tienda-disponibilidad' => 120,
            // Diez por minuto: más que eso ya es probar códigos al azar.
            'tienda-descuento' => 10,
            'tienda-checkout' => 10,
            'tienda-comprobante' => 60,
            'tienda-retorno' => 30,
        ] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)->by($nombre . '|' . $request->ip()));
        }
    }
}
