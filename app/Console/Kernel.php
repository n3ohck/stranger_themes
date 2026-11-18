<?php

namespace App\Console;

use App\Console\Commands\RememberCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('remember:command')->everyFifteenMinutes();
        // Cada minuto: el apartado de la tienda dura 5 y al vencer hay que cerrar
        // su sesión de Stripe (que dura al menos 30) para que no se pueda pagar.
        $schedule->command('tienda:limpiar-pendientes')->everyMinute()->withoutOverlapping();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
