<?php

namespace App\Actions\Tienda;

use App\Actions\VentaAction;
use App\Jobs\ComprobanteDigitalJob;
use App\Models\CompraPendiente;
use App\Models\Reserva;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Convierte una compra pagada en venta y reservaciones.
 *
 * Es idempotente a propósito: Stripe puede avisar del mismo pago varias veces (el
 * regreso del cliente al sitio y el webhook llegan por separado, y los webhooks se
 * reintentan). Si la compra ya tiene venta, se devuelve esa misma; si dos avisos
 * entran a la vez, el índice único de referencia_pago corta al segundo.
 */
class ConfirmarCompraAction
{
    public function ejecutar(CompraPendiente $compra, string $paymentIntent): Venta
    {
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

        try {
            $venta = DB::transaction(fn () => $this->crearVenta($compra, $paymentIntent));
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
            'precio' => $producto->precio,
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
