<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Suite de seguridad de la tienda en línea, por HTTP contra un servidor real.
 *
 * Todas las pruebas mandan peticiones que el servidor debe rechazar antes de
 * apartar o cobrar nada: inyección, XSS, bots, firmas falsas, regresos
 * manipulados. No crea compras ni toca Stripe, así que se puede correr contra
 * staging. Las de límite de peticiones van al final y gastan el cupo de tu IP
 * durante un minuto; --sin-limites las omite.
 *
 *   php artisan tienda:seguridad
 *   php artisan tienda:seguridad --url=https://staging.ejemplo.com
 */
class TiendaSeguridadCommand extends Command
{
    protected $signature = 'tienda:seguridad
                            {--url= : URL base del sitio (por omisión APP_URL)}
                            {--sin-limites : Omite las pruebas de límite de peticiones}';

    protected $description = 'Prueba la tienda en línea contra inyección, XSS, bots, abuso y manipulación del pago';

    private string $base;

    private array $resultados = [];

    public function handle()
    {
        $this->base = rtrim($this->option('url') ?: config('app.url'), '/');
        $this->info("Probando {$this->base}");

        $sucursal = Sucursal::query()->value('id') ?? 1;
        $producto = Producto::query()->withoutGlobalScopes()->where('visible_en_tienda', true)->value('id') ?? 1;
        $fecha = Carbon::tomorrow()->toDateString();

        // Un checkout que pasaría la validación salvo por lo que cada prueba rompe.
        $compra = fn (array $cambios = []) => array_merge([
            'sucursal_id' => $sucursal,
            'producto_id' => $producto,
            'personas' => 2,
            'fecha' => $fecha,
            'hora' => '18:00',
            'nombre' => 'Prueba Seguridad',
            'email' => 'seguridad@example.com',
            'tiempo_llenado' => 8000,
            'sitio_web' => '',
            'clave_idempotencia' => (string) Str::uuid(),
        ], $cambios);

        $this->seccion('Cabeceras de seguridad');
        $pagina = $this->http()->get('/comprar');
        $this->verificar('CSP en /comprar', str_contains($pagina->header('Content-Security-Policy'), "script-src 'self'"));
        $this->verificar('No se puede incrustar en iframe', $pagina->header('X-Frame-Options') === 'DENY');
        $this->verificar('nosniff en /comprar', $pagina->header('X-Content-Type-Options') === 'nosniff');
        $this->verificar('nosniff en el API', $this->http()->get('/tienda-api/catalogo')->header('X-Content-Type-Options') === 'nosniff');

        $this->seccion('Inyección SQL');
        foreach ([
            'producto_id' => "1 OR 1=1",
            'sucursal_id' => "1; DROP TABLE ventas",
            'fecha' => "2026-10-30' OR '1'='1",
        ] as $campo => $carga) {
            $respuesta = $this->http()->get('/tienda-api/disponibilidad', array_merge([
                'sucursal_id' => $sucursal, 'producto_id' => $producto, 'fecha' => $fecha, 'personas' => 2,
            ], [$campo => $carga]));
            $this->verificar("disponibilidad rechaza {$campo} manipulado", $respuesta->status() === 422, $respuesta);
        }

        $respuesta = $this->http()->get('/tienda-api/descuento', [
            'producto_id' => $producto, 'personas' => 2, 'codigo' => "' OR 1=1 --",
        ]);
        $this->verificar('descuento rechaza código con SQL', $respuesta->status() === 422, $respuesta);

        $respuesta = $this->http()->get('/tienda-api/comprobante/' . rawurlencode("' OR '1'='1"));
        $this->verificar('comprobante no responde a referencia inventada', $respuesta->status() === 404, $respuesta);

        $this->seccion('XSS e inyección de cabeceras');
        $respuesta = $this->http()->post('/tienda-api/checkout', $compra(['nombre' => '<script>alert(1)</script>']));
        $this->verificar('nombre con <script> rechazado', $this->rechazadoPor($respuesta, 'nombre'), $respuesta);

        $respuesta = $this->http()->post('/tienda-api/checkout', $compra(['email' => "a@b.com\r\nBcc: x@y.com"]));
        $this->verificar('correo con salto de línea rechazado', $this->rechazadoPor($respuesta, 'email'), $respuesta);

        $respuesta = $this->http()->post('/tienda-api/checkout', $compra(['telefono' => '<img src=x onerror=alert(1)>']));
        $this->verificar('teléfono con HTML rechazado', $this->rechazadoPor($respuesta, 'telefono'), $respuesta);

        $respuesta = $this->http()->post('/tienda-api/checkout', $compra(['clave_idempotencia' => '../../etc/passwd']));
        $this->verificar('clave de idempotencia manipulada rechazada', $this->rechazadoPor($respuesta, 'clave_idempotencia'), $respuesta);

        $this->seccion('Anti-bots (honeypot)');
        $respuesta = $this->http()->post('/tienda-api/checkout', $compra(['sitio_web' => 'http://spam.example']));
        $this->verificar('campo trampa lleno rechazado', $this->rechazadoPor($respuesta, 'compra'), $respuesta);

        $respuesta = $this->http()->post('/tienda-api/checkout', $compra(['tiempo_llenado' => 400]));
        $this->verificar('llenado en menos de 3 s rechazado', $this->rechazadoPor($respuesta, 'compra'), $respuesta);

        $datos = $compra();
        unset($datos['tiempo_llenado']);
        $respuesta = $this->http()->post('/tienda-api/checkout', $datos);
        $this->verificar('petición directa sin tiempo de llenado rechazada', $this->rechazadoPor($respuesta, 'compra'), $respuesta);

        $this->seccion('Manipulación del pago');
        $respuesta = $this->http()->withHeaders(['Stripe-Signature' => 't=1,v1=falsa'])
            ->withBody('{"type":"checkout.session.completed"}', 'application/json')
            ->post('/tienda-api/webhook/stripe');
        $this->verificar('webhook con firma falsa rechazado', in_array($respuesta->status(), [400, 503], true), $respuesta);

        $respuesta = $this->http()->withOptions(['allow_redirects' => false])
            ->get('/comprar/retorno', ['compra' => (string) Str::uuid(), 'session_id' => 'cs_test_inventada']);
        $this->verificar('regreso con compra inventada no confirma nada', $respuesta->status() === 302
            && ! str_contains((string) $respuesta->header('Location'), '/gracias/'), $respuesta);

        if (! $this->option('sin-limites')) {
            $this->seccion('Límite de peticiones (gasta el cupo de tu IP 1 minuto)');
            $this->verificar('descuento corta a los 10 por minuto', $this->llegaA429('/tienda-api/descuento', 'get', [
                'producto_id' => $producto, 'personas' => 2, 'codigo' => 'NOEXISTE',
            ], 12));
            $this->verificar('checkout corta a los 10 por minuto', $this->llegaA429('/tienda-api/checkout', 'post', $compra([
                'sitio_web' => 'bot',
            ]), 12));
        }

        $fallas = collect($this->resultados)->where('ok', false)->count();
        $this->newLine();
        $this->line(sprintf('%d pruebas, %d fallas', count($this->resultados), $fallas));

        return $fallas ? 1 : 0;
    }

    private function http(): PendingRequest
    {
        // withoutVerifying: los certificados de Valet y de algunos staging son locales.
        return Http::baseUrl($this->base)->acceptJson()->withoutVerifying()->timeout(15);
    }

    private function rechazadoPor(Response $respuesta, string $campo): bool
    {
        return $respuesta->status() === 422 && array_key_exists($campo, (array) $respuesta->json('errors'));
    }

    private function llegaA429(string $ruta, string $metodo, array $datos, int $intentos): bool
    {
        for ($i = 0; $i < $intentos; $i++) {
            if ($this->http()->{$metodo}($ruta, $datos)->status() === 429) {
                return true;
            }
        }

        return false;
    }

    private function seccion(string $titulo): void
    {
        $this->newLine();
        $this->line("<comment>{$titulo}</comment>");
    }

    private function verificar(string $prueba, bool $ok, ?Response $respuesta = null): void
    {
        $this->resultados[] = ['prueba' => $prueba, 'ok' => $ok];

        if ($ok) {
            $this->line("  <info>✔</info> {$prueba}");

            return;
        }

        $detalle = $respuesta ? " (HTTP {$respuesta->status()}: " . Str::limit($respuesta->body(), 120) . ')' : '';
        $this->line("  <error>✘</error> {$prueba}{$detalle}");
    }
}
