<?php

namespace App\Mail;

use App\Models\CompraPendiente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su pago llegó después de que venció el apartado, el
 * horario ya no tenía lugar y se le devolvió el dinero.
 */
class ReembolsoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private CompraPendiente $compra)
    {
    }

    public function build()
    {
        return $this->subject('Stranger Themes: devolvimos tu pago')
            ->view('emails.reembolso', ['compra' => $this->compra]);
    }
}
