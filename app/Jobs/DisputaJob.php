<?php

namespace App\Jobs;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DisputaJob implements ShouldQueue
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
        $this->sale->load([
            'sucursal',
            'reservaciones' => function ($q) {
                $q->with('producto');
            }
        ]);

        // El destinatario estaba escrito directamente en el código. Ahora sale de
        // config/mail.php (variable DISPUTAS_EMAIL) y, si la sucursal tiene correo
        // propio, también se le avisa: con varias sucursales operando, la disputa
        // le sirve a quien atiende esa sucursal.
        $destinatarios = collect([
            config('mail.disputas'),
            optional($this->sale->sucursal)->email,
        ])->filter()->unique()->values();

        if ($destinatarios->isEmpty()) {
            Log::warning('Disputa sin destinatario configurado', ['venta_id' => $this->sale->id]);

            return;
        }

        Mail::to($destinatarios->all())->send(new \App\Mail\DisputaMail($this->sale));
    }
}
