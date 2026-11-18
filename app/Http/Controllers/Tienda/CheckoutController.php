<?php

namespace App\Http\Controllers\Tienda;

use App\Actions\Tienda\ApartarCompraAction;
use App\Actions\Tienda\ConfirmarCompraAction;
use App\Actions\Tienda\VencerApartadoAction;
use App\Http\Controllers\Controller;
use App\Models\CompraPendiente;
use App\Support\Tienda\PasarelaStripe;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * Apartados que una misma IP puede crear en VENTANA_APARTADOS_MIN minutos.
     * Cada apartado bloquea lugares 5 minutos: sin tope, un script podía apartar
     * todos los horarios del día sin pagar ninguno. Ahora se aparta al elegir el
     * horario y el cliente cambia de opinión varias veces, así que el tope es más
     * holgado que cuando se apartaba solo al pulsar Pagar.
     */
    public const APARTADOS_POR_IP = 12;

    public const VENTANA_APARTADOS_MIN = 10;

    /** Milisegundos mínimos entre que carga la pantalla y se aparta o se paga. */
    public const MINIMO_MS_LLENADO = 3000;

    private const REGLA_HORARIO = [
        'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
        'producto_id' => ['required', 'integer', 'exists:productos,id'],
        'personas' => ['required', 'integer', 'min:1', 'max:20'],
        'fecha' => ['required', 'date_format:Y-m-d'],
        // Un tour manda `hora`. Un paquete manda `horas`: una por recorrido, en
        // el orden del paquete. Sin `horas`, el paquete se encadena desde `hora`
        // (así seguía funcionando la pantalla anterior que quede en caché).
        'hora' => ['required_without:horas', 'nullable', 'date_format:H:i'],
        'horas' => ['nullable', 'array', 'max:10'],
        'horas.*' => ['required', 'date_format:H:i'],
    ];

    private const REGLA_REFERENCIA = ['nullable', 'string', 'regex:/^[0-9a-f-]{36}$/'];

    public function __construct(private PasarelaStripe $pasarela)
    {
    }

    /**
     * Aparta el horario en cuanto el cliente lo elige. Desde aquí corren los
     * minutos del apartado, también mientras paga. `reemplaza` es su apartado
     * anterior (cambió de horario, personas o fecha): se suelta primero.
     */
    public function apartar(Request $request, ApartarCompraAction $apartar)
    {
        $this->rechazarBots($request);

        $datos = $request->validate(self::REGLA_HORARIO + [
            'reemplaza' => self::REGLA_REFERENCIA,
        ], ['personas.min' => 'Indica al menos un participante.']);

        if (! empty($datos['reemplaza'])) {
            $anterior = CompraPendiente::where('referencia', $datos['reemplaza'])->first();

            if ($anterior) {
                $this->soltar($anterior);
            }
        }

        if ($respuesta = $this->topeDeApartados($request)) {
            return $respuesta;
        }

        $compra = $apartar->apartar($datos);

        return response()->json($this->datosDelApartado($compra), 201);
    }

    /**
     * Inicio del pago. Con `referencia` paga el apartado que se hizo al elegir el
     * horario. Sin ella (pantalla anterior en caché) aparta y paga en un paso.
     */
    public function crear(Request $request, ApartarCompraAction $apartar)
    {
        $this->rechazarBots($request);

        $conApartado = filled($request->input('referencia'));

        $datos = $request->validate(($conApartado ? [] : self::REGLA_HORARIO) + [
            'referencia' => self::REGLA_REFERENCIA,
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
            'referencia.regex' => 'Recarga la página e intenta de nuevo.',
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

        // Con apartado, el candado es por apartado: dos clics en Pagar sobre el
        // mismo apartado devuelven la misma sesión. Sin él, por intento.
        $clave = $conApartado ? 'apartado-' . $datos['referencia'] : ($datos['clave_idempotencia'] ?? null);

        if (! $clave) {
            return $this->iniciarPago($request, $datos, $apartar);
        }

        try {
            return Cache::lock('tienda-checkout:' . $clave, 30)
                ->block(15, fn () => $conApartado
                    ? $this->pagarApartado($datos, $apartar)
                    : $this->iniciarUnaVez($request, $datos, $apartar, $clave));
        } catch (LockTimeoutException $e) {
            return response()->json([
                'error' => 'Seguimos procesando tu intento anterior. Espera unos segundos e intenta de nuevo.',
            ], 409);
        }
    }

    /** Paga un apartado hecho al elegir el horario. */
    private function pagarApartado(array $datos, ApartarCompraAction $apartar)
    {
        $compra = CompraPendiente::where('referencia', $datos['referencia'])->first();

        if (! $compra) {
            return response()->json(['error' => 'No encontramos tu apartado. Elige el horario de nuevo.'], 404);
        }

        // Ya tiene sesión (doble clic, o regresó de Stripe y vuelve a pagar): se
        // devuelve la misma. Cambiar los datos ahora no cuadraría con lo que cobra.
        if ($compra->venta_id || $compra->stripe_session_id) {
            return $this->reanudar($compra);
        }

        if (! $compra->vigente()) {
            return $this->apartadoVencido();
        }

        $compra = $apartar->completar($compra, $datos);

        return $this->crearSesion($compra, false);
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

        if ($compra->vigente() && $compra->stripe_session_id) {
            try {
                $url = $this->pasarela->urlSesion($compra->stripe_session_id);

                if ($url) {
                    return response()->json($this->datosDelApartado($compra) + [
                        'compra' => $compra->referencia,
                        'url' => $url,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo recuperar la sesión de un intento repetido', [
                    'compra' => $compra->referencia,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }

        return $this->apartadoVencido();
    }

    private function apartadoVencido()
    {
        return response()->json([
            'error' => 'Se acabó el tiempo para pagar y el horario se liberó. Elige uno de nuevo, por favor.',
            'vencido' => true,
        ], 409);
    }

    /** Pantalla anterior en caché: aparta y paga en un paso. */
    private function iniciarPago(Request $request, array $datos, ApartarCompraAction $apartar)
    {
        if ($respuesta = $this->topeDeApartados($request)) {
            return $respuesta;
        }

        return $this->crearSesion($apartar->ejecutar($datos), true);
    }

    private function topeDeApartados(Request $request)
    {
        $llave = 'tienda-apartar:' . $request->ip();

        if (RateLimiter::tooManyAttempts($llave, self::APARTADOS_POR_IP)) {
            Log::warning('Tope de apartados por IP', ['ip' => $request->ip()]);

            return response()->json([
                'error' => 'Hiciste demasiados intentos de compra seguidos. Espera unos minutos e intenta de nuevo.',
            ], 429);
        }

        RateLimiter::hit($llave, self::VENTANA_APARTADOS_MIN * 60);

        return null;
    }

    /**
     * @param  bool  $liberarSiFalla  En el flujo de un paso el apartado se acaba de
     *                                crear para este pago: si Stripe falla se suelta.
     *                                Con apartado previo se conserva para reintentar.
     */
    private function crearSesion(CompraPendiente $compra, bool $liberarSiFalla)
    {
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
                // Stripe no acepta sesiones de menos de 30 minutos. El apartado dura
                // 5: tienda:limpiar-pendientes cierra la sesión cuando vence.
                'expires_at' => now()->addMinutes(ApartarCompraAction::MINUTOS_SESION_STRIPE)->timestamp,
                // Stripe no deja poner un reloj en su página: se avisa la hora
                // límite junto al botón de pagar.
                'custom_text' => [
                    'submit' => ['message' => $this->avisoDeApartado($compra)],
                ],
                'success_url' => route('tienda.retorno') . '?compra=' . $compra->referencia . '&session_id={CHECKOUT_SESSION_ID}',
                // Con la referencia, la tienda muestra cuánto le queda al apartado
                // y deja retomar el pago o soltar el horario.
                'cancel_url' => url('/comprar?cancelado=1&compra=' . $compra->referencia),
            ], [
                // Si stripe-php reintenta por red, Stripe devuelve la misma sesión
                // en lugar de crear otra para la misma compra.
                'idempotency_key' => 'checkout-' . $compra->referencia,
            ]);
        } catch (\Throwable $e) {
            if ($liberarSiFalla) {
                $compra->forceFill(['estado' => 'fallida'])->save();
            }

            Log::error('No se pudo crear la sesión de pago', [
                'compra' => $compra->referencia,
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No pudimos iniciar el pago. Intenta de nuevo.'], 502);
        }

        $compra->forceFill(['stripe_session_id' => $sesion->id])->save();

        return response()->json($this->datosDelApartado($compra) + [
            'compra' => $compra->referencia,
            'url' => $sesion->url,
        ]);
    }

    /** Lo que la pantalla necesita para la cuenta regresiva. */
    private function datosDelApartado(CompraPendiente $compra): array
    {
        return [
            'referencia' => $compra->referencia,
            'segundos_restantes' => $compra->segundosRestantes(),
            'expira_en' => $compra->expira_en->toIso8601String(),
            'minutos_apartado' => ApartarCompraAction::MINUTOS_APARTADO,
            'total' => $compra->total,
        ];
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

    /**
     * Estado del apartado para la cuenta regresiva de la tienda.
     *
     * Los segundos restantes los calcula el servidor: el reloj del cliente puede
     * estar adelantado o atrasado y la pantalla mostraría un tiempo que no es.
     * La referencia es un UUID que solo conoce quien inició la compra, igual que
     * en el comprobante.
     */
    public function apartado(string $referencia, VencerApartadoAction $vencer)
    {
        $compra = CompraPendiente::with('producto')->where('referencia', $referencia)->first();

        if (! $compra) {
            return response()->json(['error' => 'No encontramos ese apartado.'], 404);
        }

        // Si ya venció, se cierra ahora su sesión de pago sin esperar al comando.
        if ($compra->expirada() && $vencer($compra)) {
            $compra->refresh();
        }

        $vigente = $compra->vigente();

        return response()->json($this->datosDelApartado($compra) + [
            'estado' => $compra->venta_id ? 'pagada' : ($compra->expirada() ? 'expirada' : $compra->estado),
            'vigente' => $vigente,
            'producto' => optional($compra->producto)->descripcion,
            'personas' => $compra->personas,
            'horarios' => collect($compra->horarios)->map(fn ($tramo) => [
                'producto' => $tramo['producto'] ?? null,
                'inicio' => $tramo['inicio'],
            ])->values(),
            // Ya fue a Stripe: la pantalla ofrece volver a la misma sesión.
            'con_pago' => (bool) $compra->stripe_session_id,
            'url_pago' => $vigente ? $this->urlDePagoAbierta($compra) : null,
        ]);
    }

    /** Suelta el horario antes de que venza el apartado. */
    public function liberar(string $referencia)
    {
        $compra = CompraPendiente::where('referencia', $referencia)->first();

        if (! $compra) {
            return response()->json(['error' => 'No encontramos ese apartado.'], 404);
        }

        return $this->soltar($compra);
    }

    /**
     * Primero se cierra la sesión de Stripe: si el horario se liberara con el
     * enlace de pago vivo, el cliente podría pagar un lugar que ya se vendió a
     * otro. Si Stripe dice que ya se pagó, no se libera nada.
     */
    private function soltar(CompraPendiente $compra)
    {
        if ($compra->estado !== 'apartada' || $compra->venta_id) {
            return response()->json(['liberada' => $compra->estado !== 'pagada' && ! $compra->venta_id]);
        }

        if ($compra->stripe_session_id) {
            try {
                if (! $this->pasarela->cerrarSesion($compra->stripe_session_id)) {
                    return response()->json([
                        'error' => 'Ese pago ya se completó. Revisa tu correo para ver tus boletos.',
                    ], 409);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo cerrar la sesión al liberar un apartado', [
                    'compra' => $compra->referencia,
                    'mensaje' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'No pudimos liberar el horario. Intenta de nuevo.'], 502);
            }
        }

        // Con candado: el webhook pudo confirmar el pago mientras se cerraba.
        $liberada = DB::transaction(function () use ($compra) {
            $fila = CompraPendiente::query()->lockForUpdate()->find($compra->id);

            if ($fila->estado !== 'apartada' || $fila->venta_id) {
                return false;
            }

            $fila->forceFill(['estado' => 'liberada'])->save();

            return true;
        });

        return response()->json(['liberada' => $liberada]);
    }

    /** URL de la sesión de Stripe si todavía se puede pagar en ella. */
    private function urlDePagoAbierta(CompraPendiente $compra): ?string
    {
        if (! $compra->stripe_session_id || ! $this->stripeConfigurado()) {
            return null;
        }

        try {
            return $this->pasarela->urlSesion($compra->stripe_session_id);
        } catch (\Throwable $e) {
            Log::warning('No se pudo consultar la sesión de un apartado', [
                'compra' => $compra->referencia,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function avisoDeApartado(CompraPendiente $compra): string
    {
        return 'Tu horario está apartado hasta las ' . $compra->expira_en->format('g:i a')
            . ' (hora de la sucursal). Si no completas el pago antes, el pago se cancela y el horario se libera para otras personas.';
    }

    /** Datos del comprobante para la pantalla de gracias. */
    public function comprobante(string $referencia)
    {
        $compra = CompraPendiente::with(['producto', 'sucursal', 'venta.reservaciones.producto'])
            ->where('referencia', $referencia)
            ->first();

        // Mensaje propio: firstOrFail() respondía con el nombre del modelo.
        if (! $compra) {
            return response()->json(['error' => 'No encontramos esa compra. Revisa el enlace de tu correo.'], 404);
        }

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
