<?php

namespace App\Console\Commands;

use App\Mail\RecordatorioMail;
use App\Models\LogNotificacion;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RememberCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remember:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia los recordatorios de las reservas a los clientes';

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
        $now = Carbon::now();
        Reserva::query()
            ->whereHas('venta', function ($query) {
                $query->whereNotNull('email');
            })
            ->with(['venta'])
            ->whereDate('fecha', $now->toDateString())
            ->whereBetween('fecha', [
                $now->addMinutes(15),
                $now->copy()->addMinutes(15) // .copy() para no mutar el objeto $now original
                ->addMinutes(15)          // Total: 30 minutos después del tiempo original
            ])
            ->orderBy('fecha')
            ->get()
            ->each(function ($reserva) {
                if ($reserva->venta && $reserva->venta->email) {
                    $log = new LogNotificacion();
                    $log->venta_id = $reserva->venta->id;
                    $log->producto_id = $reserva->producto_id;
                    $log->sucursal_id = $reserva->venta->sucursal_id;
                    $log->email = $reserva->venta->email;
                    $log->motivo = 'recordatorio';
                    $log->save();
                    Mail::to($reserva->venta->email)
                        ->send(new RecordatorioMail($reserva->venta));
                }
            });
    }
}
