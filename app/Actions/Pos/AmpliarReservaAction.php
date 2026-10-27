<?php

namespace App\Actions\Pos;

use App\Actions\VentaAction;
use App\Models\Apertura;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Venta;
use App\Models\VentaProducto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Cobra las personas que se suman a una reservación ya vendida.
 *
 * El dinero entra como una venta nueva del turno, ligada a la original por
 * venta_original_id: así el corte cuadra con lo que hay en el cajón, los reportes
 * reflejan el cobro y queda el registro de qué se amplió. La reservación se
 * actualiza en la misma transacción, solo si el pago se registra.
 *
 * Decisiones del negocio:
 * - Las personas extra pagan lo mismo que se cobró por persona en la venta
 *   original, con su descuento.
 * - En un paquete se suman a todos sus recorridos y se cobra el paquete: un
 *   paquete lleva la misma gente en cada recorrido.
 * - Bajar personas no pasa por aquí: se ajusta sin reembolso desde la edición.
 */
class AmpliarReservaAction extends RegistrarVentaAction
{
    /**
     * Lo que costaría pasar la reservación a $personas, sin registrar nada.
     */
    public function cotizar(Reserva $reserva, int $personas): array
    {
        $extra = $personas - (int) $reserva->cantidad_personas;

        if ($extra <= 0) {
            throw ValidationException::withMessages([
                'personas' => 'Para cobrar una ampliación el número de personas tiene que subir.',
            ]);
        }

        $linea = $this->lineaDe($reserva);

        // Sin línea que la respalde (reservación capturada en el panel, o a la que
        // se le cambió el recorrido) se cobra el precio de catálogo, sin descuento.
        $producto = $linea ? $linea->producto : $reserva->producto;
        $cantidadLinea = $linea ? max(1.0, (float) $linea->cantidad) : 1.0;
        $precio = $linea ? (float) $linea->precio : (float) $producto->precio;
        $descuentoUnitario = $linea ? (float) $linea->descuento / $cantidadLinea : 0.0;
        $totalUnitario = $linea ? (float) $linea->total / $cantidadLinea : $precio;

        return [
            'extra' => $extra,
            'producto' => $producto,
            'linea' => $linea,
            'precio' => $precio,
            'descuento_unitario' => round($descuentoUnitario, 2),
            'total_unitario' => round($totalUnitario, 2),
            'descuento' => round($descuentoUnitario * $extra, 2),
            'total' => round($totalUnitario * $extra, 2),
            'reservas' => $this->reservasAfectadas($reserva, $producto),
        ];
    }

    public function ampliar(Reserva $reserva, int $personas, array $pagos, Apertura $apertura): Venta
    {
        $cotizacion = $this->cotizar($reserva, $personas);
        $this->validarPagos($pagos, $cotizacion['total']);

        $original = $reserva->venta;
        $linea = $cotizacion['linea'];

        $venta = Venta::create([
            'user_id' => $this->user->id,
            'sucursal_id' => $this->sucursalId,
            'apertura_id' => $apertura->id,
            'venta_original_id' => optional($original)->id,
            'origen' => 'pos',
            'folio' => VentaAction::FOLIO_PENDIENTE,
            'total' => $cotizacion['total'],
            'descuento' => $cotizacion['descuento'],
            'porcentaje_descuento' => $linea ? (float) $linea->porcentaje_descuento : 0,
            'descuento_id' => optional($linea)->descuento_id,
            'codigo_descuento' => optional($linea)->codigo_descuento,
            'nombre' => $reserva->nombre_cliente,
            'created_at' => Carbon::now(),
        ]);

        VentaAction::sellarFolio($venta);

        VentaProducto::create([
            'venta_id' => $venta->id,
            'producto_id' => $cotizacion['producto']->id,
            'precio' => $cotizacion['precio'],
            'cantidad' => $cotizacion['extra'],
            'total' => $cotizacion['total'],
            'descuento_id' => optional($linea)->descuento_id,
            'codigo_descuento' => optional($linea)->codigo_descuento,
            'descuento' => $cotizacion['descuento'],
            'porcentaje_descuento' => $linea ? (float) $linea->porcentaje_descuento : 0,
        ]);

        $this->crearPagos($venta, $pagos, $cotizacion['total']);

        // RevisionableTrait deja en el historial de la reservación quién la cambió
        // y de cuántas a cuántas personas.
        foreach ($cotizacion['reservas'] as $afectada) {
            $afectada->cantidad_personas = (int) $afectada->cantidad_personas + $cotizacion['extra'];
            $afectada->save();
        }

        return $venta->fresh(['productos.producto', 'pagos', 'reservaciones.producto', 'sucursal', 'user', 'ventaOriginal']);
    }

    /**
     * La línea de la venta original que generó esta reservación: la del propio
     * recorrido, o la del paquete que lo incluye.
     */
    private function lineaDe(Reserva $reserva): ?VentaProducto
    {
        if (! $reserva->venta) {
            return null;
        }

        $lineas = $reserva->venta->productos()->with('producto')->get();
        $productoId = (int) $reserva->producto_id;

        return $lineas->first(fn (VentaProducto $l) => (int) $l->producto_id === $productoId)
            ?? $lineas->first(fn (VentaProducto $l) => optional($l->producto)->tipo === 'tour_paquete'
                && $this->toursDe($l->producto)->contains($productoId));
    }

    /**
     * La reservación misma, o en un paquete todos sus recorridos de esa venta.
     * Si la venta trae dos grupos del mismo paquete, se toman los recorridos con
     * las mismas personas que la reservación tocada: son los del mismo grupo.
     */
    private function reservasAfectadas(Reserva $reserva, Producto $cobrado): Collection
    {
        if ($cobrado->tipo !== 'tour_paquete' || ! $reserva->venta_id) {
            return collect([$reserva]);
        }

        $hermanas = Reserva::query()
            ->where('venta_id', $reserva->venta_id)
            ->where('estado', 'confirmada')
            ->where('id', '!=', $reserva->id)
            ->where('producto_id', '!=', $reserva->producto_id)
            ->whereIn('producto_id', $this->toursDe($cobrado))
            ->where('cantidad_personas', $reserva->cantidad_personas)
            ->get()
            ->unique('producto_id');

        return collect([$reserva])->merge($hermanas)->values();
    }

    private function toursDe(Producto $paquete): Collection
    {
        return collect($paquete->tours ?? [])->pluck('producto_id')->map(fn ($id) => (int) $id);
    }
}
