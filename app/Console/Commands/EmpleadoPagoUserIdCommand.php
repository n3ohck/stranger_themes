<?php

namespace App\Console\Commands;

use App\Models\Apertura;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EmpleadoPagoUserIdCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'empleado:pago-user-id-apertura';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Establece el user_id en los pagos de empleados que no tienen user_id y que pertenecen a una apertura';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $pagos = \App\Models\EmpleadoPago::query()
            ->whereNull('user_id')
            ->get();

        foreach ($pagos as $pago) {
            $date = Carbon::parse($pago->created_at);
            $endDate = $date->copy();
            $apertura = Apertura::query()
                ->whereBetween('created_at', [
                    $date->startOfDay(),
                    $endDate->endOfDay()
                ])->first();
            if (!$apertura) {
                continue;
            }
            $pago->user_id = $apertura->user_id;
            $pago->save();
            $this->info("Pago de empleado ID {$pago->id} actualizado con user_id {$pago->user_id}");
        }

        return 0; // Indica que el comando se ejecutó correctamente
    }
}
