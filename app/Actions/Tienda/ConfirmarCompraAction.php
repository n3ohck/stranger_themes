<?php

namespace App\Actions\Tienda;

use App\Actions\VentaAction;
use App\Jobs\ComprobanteDigitalJob;
use App\Mail\ReembolsoMail;
use App\Models\CompraPendiente;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Support\Tienda\Disponibilidad;
use App\Support\Tienda\PasarelaStripe;

/**
 * Convierte una compra pagada en venta y reservaciones.
 *
 * Es idempotente a propósito: Stripe puede avisar del mismo pago varias veces (el
 * regreso del cliente al sitio y el webhook llegan por separado, y los webhooks se
 * reintentan). Si la compra ya tiene venta, se devuelve esa misma; si dos avisos
 * entran a la vez, el índice único de referencia_pago corta al segundo.
 *
 * Pago tardío: el apartado dura 5 minutos y la sesión de Stripe al menos 30, así
 * que alguien puede alcanzar a pagar después de que venció (justo antes de que
 * tienda:limpiar-pendientes cerrara la sesión). Si el horario sigue teniendo
 * lugar se confirma igual; si ya no, se reembolsa el pago completo, la compra
 * queda 'reembolsada' sin venta y se le avisa al cliente por correo.
 */
class ConfirmarCompraAction
{
    public function __construct(private PasarelaStripe $pasarela)
    {
    }

    /**
     * @return Venta|null null cuando el pago llegó tarde, sin lugar, y se reembolsó.
     */
    public function ejecutar(CompraPendiente $compra, string $paymentIntent): ?Venta
    {
        $nueva = false;
        $reembolsar = false;

        try {
            // Candado sobre la fila de la compra: el regreso del cliente y el
            // webhook pueden llegar en el mismo instante. El segundo espera aquí,
            // ve la venta del primero y la devuelve sin crear otra ni mandar otro
            // correo. El índice único de referencia_pago queda como respaldo.
            $venta = DB::transaction(function () use (&$compra, $paymentIntent, &$nueva, &$reembolsar) {
                $compra = CompraPendiente::query()->lockForUpdate()->findOrFail($compra->id);

                if ($compra->venta_id) {
                    return Venta::withoutGlobalScopes()->findOrFail($compra->venta_id);
                }

                $existente = Venta::query()
                    ->withoutGlobalScopes()
                    ->where('referencia_pago', $paymentIntent)
                    ->first();

                if ($existente) {
                    $compra->forceFill([
                        'estado' => 'pagada',
                        'venta_id' => $existente->id,
                        'stripe_payment_intent' => $paymentIntent,
                    ])->save();

                    return $existente;
                }

                // Ya se reembolsó en un aviso anterior: no hay nada más que hacer.
                if ($compra->estado === 'reembolsada') {
                    return null;
                }

                if (! $compra->vigente() && ! $this->sigueHabiendoLugar($compra)) {
                    $reembolsar = true;

                    return null;
                }

                if (! $compra->vigente()) {
                    Log::info('Pago tardío aceptado: el horario seguía con lugar', ['compra' => $compra->referencia]);
                }

                $nueva = true;

                return $this->crearVenta($compra, $paymentIntent);
            });
        } catch (QueryException $e) {
            // Dos avisos simultáneos del mismo pago: gana el primero y este se
            // engancha a la venta que ya quedó registrada.
            if (! str_contains($e->getMessage(), 'ventas_referencia_pago_unique')) {
                throw $e;
            }

            Log::warning('Aviso de pago duplicado resuelto por el índice único', [
                'compra' => $compra->referencia,
                'payment_intent' => $paymentIntent,
            ]);

            $venta = Venta::withoutGlobalScopes()->where('referencia_pago', $paymentIntent)->firstOrFail();

            $compra->forceFill(['estado' => 'pagada', 'venta_id' => $venta->id])->save();

            return $venta;
        }

        if ($reembolsar) {
            $this->reembolsar($compra, $paymentIntent);

            return null;
        }

        // Solo quien creó la venta manda el correo: los avisos repetidos no.
        if (! $nueva || ! $venta) {
            return $venta;
        }

        // El comprobante se envía fuera de la transacción: con QUEUE_CONNECTION=sync
        // el correo se manda dentro del request, y un fallo de correo no debe tirar
        // una venta que el cliente ya pagó.
        try {
            ComprobanteDigitalJob::dispatch($venta->id);
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el comprobante de una compra en línea', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        return $venta;
    }

    /**
     * El apartado ya no cuenta como ocupación (venció o se liberó), así que se
     * pregunta si el grupo todavía cabe en sus horarios como cualquier otro.
     */
    private function sigueHabiendoLugar(CompraPendiente $compra): bool
    {
        $sucursal = Sucursal::find($compra->sucursal_id);

        return $sucursal
            && Disponibilidad::tramosConCupo($sucursal, $compra->horarios ?? [], (int) $compra->personas, $compra->id);
    }

    /**
     * Reembolsa fuera de la transacción (es una llamada a Stripe). Si Stripe falla
     * se lanza la excepción: el webhook responde error, Stripe lo reintenta y se
     * vuelve a intentar el reembolso, con la misma llave de idempotencia.
     */
    private function reembolsar(CompraPendiente $compra, string $paymentIntent): void
    {
        try {
            $this->pasarela->reembolsar($paymentIntent, $compra->referencia);
        } catch (\Throwable $e) {
            Log::critical('No se pudo reembolsar un pago tardío sin lugar', [
                'compra' => $compra->referencia,
                'payment_intent' => $paymentIntent,
                'mensaje' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Solo el aviso que marca la compra manda el correo: si llegan el regreso
        // del cliente y el webhook, el segundo no lo repite.
        $marcada = CompraPendiente::query()
            ->whereKey($compra->id)
            ->where('estado', '!=', 'reembolsada')
            ->whereNull('venta_id')
            ->update(['estado' => 'reembolsada', 'stripe_payment_intent' => $paymentIntent]);

        Log::warning('Pago tardío reembolsado: el horario ya no tenía lugar', [
            'compra' => $compra->referencia,
            'payment_intent' => $paymentIntent,
        ]);

        if (! $marcada || ! $compra->email) {
            return;
        }

        try {
            Mail::to($compra->email)->send(new ReembolsoMail($compra->fresh('producto')));
        } catch (\Throwable $e) {
            Log::error('No se pudo avisar el reembolso al cliente', [
                'compra' => $compra->referencia,
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private function crearVenta(CompraPendiente $compra, string $paymentIntent): Venta
    {
        $producto = $compra->producto()->withoutGlobalScopes()->first();
        $primerTramo = Carbon::parse($compra->horarios[0]['inicio']);

        $venta = Venta::create([
            'user_id' => 1,
            'sucursal_id' => $compra->sucursal_id,
            'origen' => 'web',
            'referencia_pago' => $paymentIntent,
            'folio' => VentaAction::FOLIO_PENDIENTE,
            'total' => $compra->total,
            'descuento' => $compra->descuento,
            'porcentaje_descuento' => $compra->porcentaje_descuento,
            'codigo_descuento' => $compra->codigo_descuento,
            'descuento_id' => $compra->descuento_id,
            'nombre' => $compra->nombre,
            'email' => $compra->email,
            'telefono' => $compra->telefono,
            // La venta se fecha cuando se pagó, no cuando es la experiencia.
            'created_at' => Carbon::now(),
        ]);

        VentaAction::sellarFolio($venta);

        VentaProducto::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            // El precio por persona que se apartó, no el de catálogo: con rangos
            // por personas depende del grupo, y el catálogo pudo cambiar mientras
            // el cliente pagaba. total + descuento = precio × personas.
            'precio' => round(((float) $compra->total + (float) $compra->descuento) / max(1, (int) $compra->personas), 2),
            'cantidad' => $compra->personas,
            'total' => $compra->total,
            'descuento' => $compra->descuento,
            'descuento_id' => $compra->descuento_id,
            'codigo_descuento' => $compra->codigo_descuento,
            'porcentaje_descuento' => $compra->porcentaje_descuento,
        ]);

        VentaPago::create([
            'venta_id' => $venta->id,
            'monto' => $compra->total,
            'cambio' => 0,
            'tipo' => 'online',
            'referencia' => $paymentIntent,
        ]);

        // Una reservación por cada recorrido: un paquete de tres genera tres.
        foreach ($compra->horarios as $tramo) {
            Reserva::create([
                'producto_id' => $tramo['producto_id'],
                'nombre_cliente' => $compra->nombre,
                'cantidad_personas' => $compra->personas,
                'fecha' => Carbon::parse($tramo['inicio']),
                'estado' => 'confirmada',
                'sucursal_id' => $compra->sucursal_id,
                'venta_id' => $venta->id,
            ]);
        }

        $compra->forceFill([
            'estado' => 'pagada',
            'venta_id' => $venta->id,
            'stripe_payment_intent' => $paymentIntent,
        ])->save();

        return $venta->fresh(['productos.producto', 'pagos', 'reservaciones.producto', 'sucursal']);
    }
}
