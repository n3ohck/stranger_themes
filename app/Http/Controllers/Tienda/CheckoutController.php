<?php

namespace App\Http\Controllers\Tienda;

use App\Actions\Tienda\ApartarCompraAction;
use App\Actions\Tienda\ConfirmarCompraAction;
use App\Http\Controllers\Controller;
use App\Models\CompraPendiente;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
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
    /**
     * Apartados que una misma IP puede tener en media hora. Cada apartado bloquea
     * horarios 30 minutos: sin este tope, un script podía apartar todos los
     * horarios de la semana sin pagar ninguno. Cuatro alcanzan para quien se
     * equivoca y vuelve a intentar.
     */
    public const APARTADOS_POR_IP = 4;

    /** Milisegundos mínimos entre que carga la pantalla y se pulsa Pagar. */
    public const MINIMO_MS_LLENADO = 3000;

    public function crear(Request $request, ApartarCompraAction $apartar)
    {
        $this->rechazarBots($request);

        $datos = $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'personas' => ['required', 'integer', 'min:1', 'max:20'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            // Solo letras, espacios, apóstrofo, punto y guion: el nombre viaja al
            // correo, al panel, al POS y a Stripe, y no tiene por qué traer < > ni
            // comillas. Blade y Vue ya escapan; esto es una segunda barrera.
            'nombre' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL\\pM\\s'.-]+$/u"],
            'email' => ['required', 'email:rfc', 'max:191'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9+()\\s-]{7,20}$/'],
            // Los códigos existentes llevan espacios y acentos ("2 X 1", "Cumpleañero").
            'codigo_descuento' => ['nullable', 'string', 'regex:/^[\\pL\\pN\\s_.-]{1,60}$/u'],
            // Una por intento de pago, generada en el navegador. Ver iniciarUnaVez().
            'clave_idempotencia' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{16,64}$/'],
        ], [
            'nombre.regex' => 'El nombre solo puede llevar letras y espacios.',
            'telefono.regex' => 'El teléfono solo puede llevar números.',
            'codigo_descuento.regex' => 'Ese código de descuento no es válido.',
            'clave_idempotencia.regex' => 'Recarga la página e intenta de nuevo.',
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

        $clave = $datos['clave_idempotencia'] ?? null;

        if (! $clave) {
            return $this->iniciarPago($request, $datos, $apartar);
        }

        try {
            return Cache::lock('tienda-checkout:' . $clave, 30)
                ->block(15, fn () => $this->iniciarUnaVez($request, $datos, $apartar, $clave));
        } catch (LockTimeoutException $e) {
            return response()->json([
                'error' => 'Seguimos procesando tu intento anterior. Espera unos segundos e intenta de nuevo.',
            ], 409);
        }
    }

    /**
     * Idempotencia del inicio de pago. El mismo intento puede llegar dos veces:
     * doble clic, o el navegador reintenta porque se cortó la conexión después de
     * que Stripe creó la sesión. Bajo un candado por clave, si el intento ya
     * existe se devuelve su sesión en vez de apartar y crear otra.
     */
    private function iniciarUnaVez(Request $request, array $datos, ApartarCompraAction $apartar, string $clave)
    {
        $previa = CompraPendiente::where('clave_idempotencia', $clave)->first();

        return $previa
            ? $this->reanudar($previa)
            : $this->iniciarPago($request, $datos, $apartar);
    }

    /** Respuesta para un intento que ya se había iniciado. */
    private function reanudar(CompraPendiente $compra)
    {
        if ($compra->venta_id) {
            return response()->json([
                'compra' => $compra->referencia,
                'url' => url('/comprar/gracias/' . $compra->referencia),
            ]);
        }

        $vigente = $compra->estado === 'apartada' && $compra->expira_en && $compra->expira_en->isFuture();

        if ($vigente && $compra->stripe_session_id) {
            try {
                $sesion = $this->stripe()->checkout->sessions->retrieve($compra->stripe_session_id);

                if ($sesion->status === 'open') {
                    return response()->json([
                        'compra' => $compra->referencia,
                        'url' => $sesion->url,
                        'expira_en' => $compra->expira_en->toIso8601String(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo recuperar la sesión de un intento repetido', [
                    'compra' => $compra->referencia,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'error' => 'Ese intento de pago ya no está vigente. Vuelve a pulsar Pagar.',
        ], 409);
    }

    private function iniciarPago(Request $request, array $datos, ApartarCompraAction $apartar)
    {
        $llave = 'tienda-apartar:' . $request->ip();

        if (RateLimiter::tooManyAttempts($llave, self::APARTADOS_POR_IP)) {
            Log::warning('Tope de apartados por IP', ['ip' => $request->ip()]);

            return response()->json([
                'error' => 'Hiciste demasiados intentos de compra seguidos. Espera unos minutos e intenta de nuevo.',
            ], 429);
        }

        $compra = $apartar->ejecutar($datos);
        RateLimiter::hit($llave, ApartarCompraAction::MINUTOS_PARA_PAGAR * 60);

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
                        'unit_amount' => $this->centavosPorPersona($compra),
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
            ], [
                // Si stripe-php reintenta por red, Stripe devuelve la misma sesión
                // en lugar de crear otra para la misma compra.
                'idempotency_key' => 'checkout-' . $compra->referencia,
            ]);
        } catch (\Throwable $e) {
            // Si Stripe falla, el apartado se libera de inmediato en vez de bloquear
            // el horario media hora por una compra que nunca va a existir.
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

            // El session_id llega en la URL y se puede cambiar: sin esto, pagar el
            // boleto más barato confirmaba cualquier otra compra con ese pago.
            if (! $this->sesionEsDeLaCompra($sesion, $compra)) {
                return redirect('/comprar?pago_invalido=1');
            }

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

        if (! $this->sesionEsDeLaCompra($sesion, $compra)) {
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

    /**
     * Honeypot y tiempo de llenado. Un bot que publica directo al API llena el
     * campo oculto "sitio_web" o no manda el tiempo que tardó en llenar la
     * pantalla; una persona tarda más de tres segundos. Se mide en el navegador
     * (milisegundos desde que cargó) para no depender de que su reloj coincida
     * con el del servidor. La respuesta es genérica para no enseñarle al bot qué
     * lo delató.
     */
    private function rechazarBots(Request $request): void
    {
        $tiempo = $request->input('tiempo_llenado');
        $esBot = filled($request->input('sitio_web'))
            || ! is_numeric($tiempo)
            || (int) $tiempo < self::MINIMO_MS_LLENADO;

        if ($esBot) {
            Log::warning('Compra en línea rechazada por anti-bot', [
                'ip' => $request->ip(),
                'honeypot' => filled($request->input('sitio_web')),
                'tiempo_llenado' => $tiempo,
            ]);

            throw ValidationException::withMessages([
                'compra' => 'No pudimos procesar tu solicitud. Recarga la página e intenta de nuevo.',
            ]);
        }
    }

    /** Importe por persona en centavos, el mismo con el que se crea la sesión. */
    private function centavosPorPersona(CompraPendiente $compra): int
    {
        return (int) round(($compra->total / $compra->personas) * 100);
    }

    /**
     * La sesión de Stripe pertenece a esta compra y cobró lo que se apartó, en
     * la moneda configurada. Cualquier diferencia se registra y no se confirma.
     */
    private function sesionEsDeLaCompra($sesion, CompraPendiente $compra): bool
    {
        $esperado = $this->centavosPorPersona($compra) * $compra->personas;

        $coincide = ($sesion->client_reference_id ?? null) === $compra->referencia
            && (int) ($sesion->amount_total ?? -1) === $esperado
            && strtolower((string) ($sesion->currency ?? '')) === strtolower(config('services.stripe.moneda', 'mxn'));

        if (! $coincide) {
            Log::warning('Sesión de Stripe que no corresponde a la compra', [
                'compra' => $compra->referencia,
                'session' => $sesion->id ?? null,
                'client_reference_id' => $sesion->client_reference_id ?? null,
                'amount_total' => $sesion->amount_total ?? null,
                'esperado' => $esperado,
            ]);
        }

        return $coincide;
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
