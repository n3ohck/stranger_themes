<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //Bind original crud controller to a custom controller
        $this->app->bind(\Backpack\PermissionManager\app\Http\Controllers\UserCrudController::class, \App\Http\Controllers\Admin\UserController::class);

        // ...
    }
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
