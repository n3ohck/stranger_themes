<?php

namespace App\Support\Tienda;

use Stripe\StripeClient;

/**
 * Las operaciones de Stripe que se hacen fuera del inicio del pago: vencer una
 * sesión cuando se acaba el apartado y reembolsar un pago que llegó tarde.
 *
 * Vive en su propia clase para poder sustituirla en las pruebas: así se prueba
 * el vencimiento y el reembolso sin llamar a Stripe.
 */
class PasarelaStripe
{
    private ?StripeClient $cliente = null;

    public function cliente(): StripeClient
    {
        return $this->cliente ??= new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Estado de la sesión: 'open', 'complete' o 'expired'.
     */
    public function estadoSesion(string $sesionId): string
    {
        return (string) $this->cliente()->checkout->sessions->retrieve($sesionId)->status;
    }

    public function urlSesion(string $sesionId): ?string
    {
        $sesion = $this->cliente()->checkout->sessions->retrieve($sesionId);

        return $sesion->status === 'open' ? $sesion->url : null;
    }

    /**
     * Cierra la sesión para que ya no se pueda pagar en ella.
     *
     * @return bool false si ya se había pagado (la sesión está 'complete'): en ese
     *              caso no hay nada que cerrar y el pago se confirmará por su lado.
     */
    public function cerrarSesion(string $sesionId): bool
    {
        $estado = $this->estadoSesion($sesionId);

        if ($estado === 'complete') {
            return false;
        }

        if ($estado === 'open') {
            $this->cliente()->checkout->sessions->expire($sesionId);
        }

        return true;
    }

    /**
     * Devuelve el pago completo. La llave de idempotencia evita un segundo
     * reembolso si el regreso del cliente y el webhook llegan los dos.
     */
    public function reembolsar(string $paymentIntent, string $referencia): void
    {
        $this->cliente()->refunds->create(
            ['payment_intent' => $paymentIntent, 'reason' => 'requested_by_customer', 'metadata' => ['compra' => $referencia]],
            ['idempotency_key' => 'reembolso-' . $referencia]
        );
    }
}
