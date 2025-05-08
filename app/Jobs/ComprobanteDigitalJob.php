<?php

namespace App\Jobs;

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
        $this->sale->load([
            'sucursal',
            'reservaciones' => function ($q) {
                $q->with('producto');
            }
        ]);

        // Send email
        Mail::to($this->sale->user)->send(new \App\Mail\ComprobanteMail($this->sale));
    }
}
