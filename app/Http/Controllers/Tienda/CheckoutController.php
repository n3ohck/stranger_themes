<?php

namespace App\Http\Controllers\Tienda;

use App\Actions\Tienda\ApartarCompraAction;
use App\Actions\Tienda\ConfirmarCompraAction;
use App\Http\Controllers\Controller;
use App\Models\CompraPendiente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Cobro con Stripe Checkout.
 *
 * Se usa la página alojada por Stripe en lugar de un formulario propio: los datos
 * de tarjeta se escriben en el dominio de Stripe y este servidor no los ve, no los
 * transmite y no los guarda.
 */
class CheckoutController extends Controller
{
    public function crear(Request $request, ApartarCompraAction $apartar)
    {
        $datos = $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'personas' => ['required', 'integer', 'min:1', 'max:20'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'nombre' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'codigo_descuento' => ['nullable', 'string', 'max:100'],
        ], [
            'nombre.required' => 'Necesitamos un nombre para la reservación.',
            'email.required' => 'Necesitamos un correo para enviarte los boletos.',
            'email.email' => 'Ese correo no parece válido.',
            'personas.min' => 'Indica al menos un participante.',
        ]);

        if (! $this->stripeConfigurado()) {
            return response()->json([
                'error' => 'El pago en línea no está configurado todavía. Escríbenos para reservar.',
            ], 503);
        }

        $compra = $apartar->ejecutar($datos);

        try {
            $sesion = $this->stripe()->checkout->sessions->create([
                'mode' => 'payment',
                'customer_email' => $compra->email,
                'client_reference_id' => $compra->referencia,
                'line_items' => [[
                    'quantity' => $compra->personas,
                    'price_data' => [
                        'currency' => config('services.stripe.moneda', 'mxn'),
                        // Precio unitario ya con el descuento aplicado. Se deriva del
                        // total apartado y no del precio de lista, para que lo que
                        // cobra Stripe sea exactamente lo que se guardó en la compra.
                        'unit_amount' => (int) round(($compra->total / $compra->personas) * 100),
                        'product_data' => [
                            'name' => $compra->producto->descripcion,
                            'description' => $this->descripcionDe($compra),
                        ],
                    ],
                ]],
                'metadata' => [
                    'compra' => $compra->referencia,
                    'sucursal_id' => (string) $compra->sucursal_id,
                    'codigo_descuento' => (string) ($compra->codigo_descuento ?? ''),
                ],
                // Stripe expira la sesión junto con el apartado, para que no quede
                // un enlace de pago vivo cuando el horario ya se liberó.
                'expires_at' => $compra->expira_en->timestamp,
                'success_url' => route('tienda.retorno') . '?compra=' . $compra->referencia . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/comprar?cancelado=1'),
            ]);
        } catch (\Throwable $e) {
            // Si Stripe falla, el apartado se libera de inmediato en vez de bloquear
            // el horario veinte minutos por una compra que nunca va a existir.
            $compra->forceFill(['estado' => 'fallida'])->save();

            Log::error('No se pudo crear la sesión de pago', [
                'compra' => $compra->referencia,
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No pudimos iniciar el pago. Intenta de nuevo.'], 502);
        }

        $compra->forceFill(['stripe_session_id' => $sesion->id])->save();

        return response()->json([
            'compra' => $compra->referencia,
            'url' => $sesion->url,
            'expira_en' => $compra->expira_en->toIso8601String(),
        ]);
    }

    /**
     * Regreso del cliente desde Stripe. Se confirma contra la API en vez de confiar
     * en la URL: llegar a esta dirección no prueba que se haya pagado.
     */
    public function retorno(Request $request, ConfirmarCompraAction $confirmar)
    {
        $compra = CompraPendiente::where('referencia', $request->query('compra'))->first();
        $sessionId = $request->query('session_id');

        if (! $compra || ! $sessionId) {
            return redirect('/comprar')->with('error', 'No encontramos esa compra.');
        }

        if ($compra->venta_id) {
            return redirect('/comprar/gracias/' . $compra->referencia);
        }

        try {
            $sesion = $this->stripe()->checkout->sessions->retrieve($sessionId);

            if ($sesion->payment_status !== 'paid') {
                return redirect('/comprar?pago_pendiente=1');
            }

            $confirmar->ejecutar($compra, $this->paymentIntentDe($sesion));
        } catch (\Throwable $e) {
            Log::error('Falló la confirmación al regresar de Stripe', [
                'compra' => $compra->referencia,
                'mensaje' => $e->getMessage(),
            ]);

            // El webhook es la segunda oportunidad: si el pago existe, la venta se
            // creará igual aunque este regreso haya fallado.
            return redirect('/comprar/gracias/' . $compra->referencia . '?procesando=1');
        }

        return redirect('/comprar/gracias/' . $compra->referencia);
    }

    /**
     * Aviso de Stripe. Es la vía confiable: llega aunque el cliente cierre el
     * navegador antes de volver al sitio.
     */
    public function webhook(Request $request, ConfirmarCompraAction $confirmar)
    {
        $secreto = config('services.stripe.webhook_secret');

        if (! $secreto) {
            Log::warning('Webhook de Stripe recibido sin secreto configurado');

            return response()->json(['error' => 'Webhook no configurado'], 503);
        }

        try {
            $evento = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secreto
            );
        } catch (\Throwable $e) {
            // Firma inválida: o no viene de Stripe, o el secreto no corresponde.
            Log::warning('Webhook de Stripe rechazado', ['mensaje' => $e->getMessage()]);

            return response()->json(['error' => 'Firma inválida'], 400);
        }

        if (! in_array($evento->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return response()->json(['recibido' => true]);
        }

        $sesion = $evento->data->object;
        $compra = CompraPendiente::where('referencia', $sesion->client_reference_id ?? '')->first();

        if (! $compra) {
            Log::warning('Webhook sin compra asociada', ['session' => $sesion->id ?? null]);

            return response()->json(['recibido' => true]);
        }

        if (($sesion->payment_status ?? null) === 'paid') {
            $confirmar->ejecutar($compra, $this->paymentIntentDe($sesion));
        }

        return response()->json(['recibido' => true]);
    }

    /** Datos del comprobante para la pantalla de gracias. */
    public function comprobante(string $referencia)
    {
        $compra = CompraPendiente::with(['producto', 'sucursal', 'venta.reservaciones.producto'])
            ->where('referencia', $referencia)
            ->firstOrFail();

        return response()->json([
            'estado' => $compra->estado,
            'nombre' => $compra->nombre,
            'email' => $compra->email,
            'personas' => $compra->personas,
            'subtotal' => round($compra->total + $compra->descuento, 2),
            'descuento' => $compra->descuento,
            'codigo_descuento' => $compra->codigo_descuento,
            'total' => $compra->total,
            'producto' => $compra->producto->descripcion,
            'sucursal' => [
                'nombre' => optional($compra->sucursal)->razon_social,
                'direccion' => optional($compra->sucursal)->direccion,
                'ubicacion' => optional($compra->sucursal)->ubicacion,
            ],
            'folio' => optional($compra->venta)->folio,
            'reservaciones' => collect($compra->horarios)->map(fn ($tramo) => [
                'producto' => $tramo['producto'] ?? null,
                'inicio' => $tramo['inicio'],
            ])->values(),
        ]);
    }

    private function paymentIntentDe($sesion): string
    {
        $intent = $sesion->payment_intent ?? null;

        // Puede venir como id o como objeto expandido.
        return is_string($intent) ? $intent : ($intent->id ?? $sesion->id);
    }

    private function descripcionDe(CompraPendiente $compra): string
    {
        $inicio = \Carbon\Carbon::parse($compra->horarios[0]['inicio']);

        $texto = $inicio->format('d/m/Y') . ' · ' . $inicio->format('g:i a') . ' · ' . $compra->personas . ' participante(s)';

        if ($compra->codigo_descuento) {
            $texto .= ' · Código ' . $compra->codigo_descuento;
        }

        return $texto;
    }

    private function stripeConfigurado(): bool
    {
        return (bool) config('services.stripe.secret');
    }

    private function stripe(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }
}
