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
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });
    }
}
