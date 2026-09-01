<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ExistenciaAction;
use App\Http\Controllers\Controller;
use App\Jobs\DisputaJob;
use App\Models\Disputa;
use App\Models\Venta;
use App\Models\VentaPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancelación de una venta en línea a petición del cliente.
 *
 * Endpoint público, así que quien llama tiene que demostrar que la compra es suya.
 * Antes bastaba con mandar un venta_id existente: como los ids son consecutivos,
 * cualquiera podía cancelar ventas ajenas recorriéndolos.
 *
 * La prueba es la referencia del cobro (el PaymentIntent de la pasarela). No es
 * adivinable y solo la tienen el cliente, el sitio y la pasarela.
 */
class DisputasController extends Controller
{
    public function set(Request $request)
    {
        $datos = $request->validate([
            'venta_id' => ['required', 'integer'],
            'referencia' => ['required', 'string', 'max:255'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [
            'venta_id.required' => 'Falta la venta a cancelar.',
            'referencia.required' => 'Falta la referencia del pago para verificar la compra.',
        ]);

        // Respuesta única para todos los casos de "no procede": distinguirlos
        // permitiría averiguar qué ids existen y qué referencias son válidas.
        $rechazo = response()->json([
            'status' => false,
            'message' => 'No se encontró una compra que coincida con esos datos.',
        ], 404);

        $venta = Venta::query()
            ->withoutGlobalScopes()
            ->where('id', $datos['venta_id'])
            ->where('origen', 'web')
            ->first();

        if (! $venta || ! $this->referenciaCoincide($venta, trim($datos['referencia']))) {
            Log::warning('Intento fallido de cancelación pública', [
                'venta_id' => $datos['venta_id'],
                'ip' => $request->ip(),
            ]);

            return $rechazo;
        }

        // Idempotente: reenviar la misma solicitud no vuelve a cancelar ni dispara
        // un segundo correo, pero tampoco es un error para el sitio.
        if ($venta->estatus === 'cancelado') {
            return response()->json([
                'status' => true,
                'message' => 'Esta compra ya estaba cancelada.',
                'data' => ['venta_id' => $venta->id, 'folio' => $venta->folio],
            ]);
        }

        try {
            $disputa = DB::transaction(function () use ($venta, $datos) {
                $venta->estatus = 'cancelado';
                $venta->fecha_cancelacion = Carbon::now();
                $venta->comentario_cancelacion = $datos['motivo'] ?? 'Cancelación solicitada desde el sitio web';
                $venta->save();

                $venta->reservaciones()->update(['estado' => 'cancelada']);

                // Devolver existencias, que la versión anterior no hacía: una venta
                // en línea de artículos descontaba inventario al crearse y al
                // cancelarla el stock se quedaba corto para siempre.
                foreach ($venta->productos as $linea) {
                    ExistenciaAction::existenciaCancelacion($linea->producto_id, (int) $linea->cantidad);
                }

                return Disputa::create(['venta_id' => $venta->id]);
            });

            DisputaJob::dispatch($venta->id);
        } catch (\Exception $e) {
            Log::error('Falló la cancelación pública de una venta', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No se pudo cancelar la compra. Intenta de nuevo.',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Compra cancelada correctamente.',
            'data' => [
                'venta_id' => $venta->id,
                'folio' => $venta->folio,
                'disputa_id' => $disputa->id,
            ],
        ]);
    }

    /**
     * La referencia puede estar en ventas.referencia_pago (ventas nuevas) o en el
     * pago en línea de la venta (las anteriores a esa columna).
     */
    private function referenciaCoincide(Venta $venta, string $referencia): bool
    {
        if ($venta->referencia_pago && hash_equals($venta->referencia_pago, $referencia)) {
            return true;
        }

        return VentaPago::query()
            ->where('venta_id', $venta->id)
            ->where('tipo', 'online')
            ->where('referencia', $referencia)
            ->exists();
    }
}
