<?php

namespace App\Mail;

use App\Models\LogNotificacion;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DisputaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    private $sale;

    public function __construct(Venta $sale)
    {
        $this->sale = $sale;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $log = new LogNotificacion();
        $log->venta_id = $this->sale->id;
        $log->producto_id = $this->sale->reservaciones[0]->producto_id;
        $log->sucursal_id = $this->sale->sucursal_id;
        $log->email = $this->sale->email;
        $log->motivo = 'disputa';
        $log->save();
        return $this->subject('Venta ' . $this->sale->folio.' en disputa.')
            ->view('emails.disputa', [
                'sale' => $this->sale,
            ]);

    }
}
