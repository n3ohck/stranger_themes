<?php

namespace App\Jobs;

use App\Models\LogNotificacion;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ComprobanteDigitalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $sale;

    public function __construct(int $saleId)
    {
        $this->sale = Venta::findOrFail($saleId);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $log = new LogNotificacion();
        $log->venta_id = $this->sale->id;
        $log->producto_id = $this->sale->reservaciones[0]->producto_id;
        $log->sucursal_id = $this->sale->sucursal_id;
        $log->email = $this->sale->email;
        $log->motivo = 'comprobante';
        $log->save();
        $this->sale->load([
            'sucursal',
            'reservaciones' => function ($q) {
                $q->with('producto');
            }
        ]);

        $this->sale->total = ($this->sale->total - $this->sale->descuento);
        // Send email
        Mail::to($this->sale->email)->send(new \App\Mail\ComprobanteMail($this->sale));
    }
}
