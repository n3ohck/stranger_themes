<?php

namespace App\Actions;

use App\Jobs\ComprobanteDigitalJob;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use App\Traits\DateTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaAction
{
    use DateTrait;

    public $user;

    public function __construct()
    {
        $this->user = backpack_user();
    }

    public function makeFolio(): string
    {
        $lastVenta = Venta::query()
            ->select(['id', 'created_at'])
            ->latest()
            ->first();
        if (!$lastVenta) return 'VTA-1';
        return 'VTA-' . ($lastVenta->id + 1);
    }

    public function do(array $ventas): array
    {
        $nuevasVentas = [];
        foreach ($ventas as $venta) {
            $venta['datetime'] = $this->makeDate($venta['datetime']);
            $existe = Venta::query()
                ->where('created_at', $venta['datetime'])
                ->where('total', $venta['total'])
                ->exists();
            if ($existe) continue;
            $nuevaVenta = Venta::create([
                'user_id' => $this->user->id,
                'descuento_id' => $venta['descuento_id'] ?? null,
                'sucursal_id' => $this->user->sucursal_id,
                'folio' => $this->makeFolio(),
                'total' => $venta['total'],
                'codigo_descuento' => $venta['codigo_descuento'] ?? null,
                'descuento' => $venta['descuento'] ?? null,
                'porcentaje_descuento' => $venta['porcentaje_descuento'] ?? null,
                'created_at' => $venta['datetime']
            ]);
            $this->makeVentaProductos($nuevaVenta->id, $venta['productos']);
            $this->makeVentaPagos($nuevaVenta->id, $venta['pagos']);

            if (isset($venta['reservaciones'])) {
                $reservaciones = $this->makeVentaReservacion($nuevaVenta->id, $venta['reservaciones']);
            }
            $nuevasVentas[] = [
                'venta_id' => $nuevaVenta->id,
                'estatus' => $nuevaVenta->estatus,
                'folio' => $nuevaVenta->folio,
                'total' => $nuevaVenta->total,
                'reservaciones' => $reservaciones ?? []
            ];
        }
        return $nuevasVentas;
    }

    public function saleOnline(array $sales): array
    {
        foreach ($sales as $sale) {
            $venta['datetime'] = $this->makeDate($sale['datetime']);
            $existe = Venta::query()
                ->where('created_at', $sale['datetime'])
                ->where('total', $sale['total'])
                ->exists();
            if ($existe) continue;

            $payments = collect($sale['pagos'])
                ->where('tipo', 'online');
            if ($payments->isEmpty()) {
                throw new \Exception('No se han encontrado pagos ONLINE para el venta', 400);
            }

            $products = collect($sale['productos']);
            $booking = collect($sale['reservaciones']);
            $newSales = $products->each(function ($product) use ($booking, $sale, &$newSales) {
                $product = (object) $product;
                $reservation = $booking->where('producto_id', $product->producto_id)->first();
                if( !$reservation ){
                    throw new \Exception('No se han encontrado reservaciones para el producto', 400);
                }
                $venta = Venta::create([
                    'user_id' => 1,
                    'descuento_id' => $product->descuento_id ?? null,
                    'sucursal_id' => $sale['sucursal_id'],
                    'folio' => $this->makeFolio(),
                    'total' => $product->total,
                    'codigo_descuento' => $product->codigo_descuento ?? null,
                    'descuento' => $product->descuento ?? null,
                    'porcentaje_descuento' => $product->porcentaje_descuento ?? null,
                    'created_at' => $this->makeDate($reservation['datetime']),
                    'nombre' => $sale['nombre'] ?? null,
                    'telefono' => $sale['telefono'] ?? null,
                    'email' => $sale['email'] ?? null,
                ]);
                $this->makeVentaProductos($venta->id, $sale['productos']);
                $this->makeVentaPagos($venta->id, $sale['pagos']);
                if (isset($venta['reservaciones'])) {
                    $reservations[] = $this->makeVentaReservacion($venta->id, $sale['reservaciones'], $sale['sucursal_id']);
                }
                if( isset($sale['email']) ){
                    ComprobanteDigitalJob::dispatch($venta->id);
                }
                return [
                    'venta_id' => $venta->id,
                    'estatus' => $venta->estatus,
                    'folio' => $venta->folio,
                    'total' => $venta->total,
                    'reservaciones' => $reservations ?? []
                ];
            });
        }
        return $newSales->toArray();
    }

    public
    function makeVentaReservacion(int $ventaId, array $reservas, $branchId = null): array
    {
        $reservasNuevas = [];
        foreach ($reservas as $reserva) {
            $reserva['datetime'] = $this->makeDate($reserva['datetime']);
            $reservasNuevas[] = Reserva::create([
                'producto_id' => $reserva['producto_id'],
                'nombre_cliente' => $reserva['name'],
                'cantidad_personas' => $reserva['number'],
                'fecha' => $reserva['datetime'],
                'estado' => 'confirmada',
                'sucursal_id' => $this->user->sucursal_id ?? $branchId,
                'venta_id' => $ventaId
            ]);
        }
        return $reservasNuevas;
    }

    public
    function makeVentaProductos(int $ventaId, array $productos): void
    {
        $totalDescuento = 0;
        $subtotal = 0;
        $descuentoDinero = 0;
        $porcentaje = 0;
        $descuentos = [];
        $descuentoId = null;
        $codigoDescuento = null;
        foreach ($productos as $producto) {
            $subtotal += $producto['precio'];

            if (isset($producto['descuentos'])) {
                foreach ($producto['descuentos'] as $descuento) {
                    $totalDescuento += $producto['precio'] - $producto['total'];
                    $descuentoDinero = $producto['precio'] - $producto['total'];
                    $porcentaje = $descuento['porcentaje_descuento'] ?? 0;
                    $descuentoId = $descuento['descuento_id'] ?? null;
                    $codigoDescuento = $descuentos['codigo_descuento'] ?? null;
                }
            }

            VentaProducto::create([
                'venta_id' => $ventaId,
                'producto_id' => $producto['producto_id'],
                'precio' => $producto['precio'],
                'cantidad' => $producto['cantidad'],
                'total' => $producto['total'],
                'descuento_id' => $descuentoId,
                'codigo_descuento' => $codigoDescuento,
                'descuento' => number_format($descuentoDinero, 2, '.', ''),
                'porcentaje_descuento' => $porcentaje
            ]);
            (new ExistenciaAction())::salidarPorVenta($producto['producto_id'], $producto['cantidad']);
        }
        $venta = Venta::find($ventaId);
        $venta->update([
            'descuento' => $totalDescuento,
            'porcentaje_descuento' => ($subtotal) ? number_format(($totalDescuento / $subtotal) * 100, 2, '.', '') : 0
        ]);
    }

    public
    function makeVentaPagos(int $ventaId, array $pagos): void
    {
        foreach ($pagos as $pago) {
            VentaPago::create([
                'venta_id' => $ventaId,
                'monto' => $pago['monto'],
                'cambio' => $pago['cambio'] ?? 0,
                'tipo' => $pago['tipo'],
                'referencia' => $pago['referencia'] ?? null
            ]);
        }
    }

    public
    function cancelVentas(array $ventas): array
    {
        $ventasCanceladas = [];
        foreach ($ventas as $venta) {
            $ventaActualizar = Venta::find($venta['venta_id']);
            if (!$ventaActualizar) throw new \Exception('No se ha encontrado la venta a cancelar');
            if ($ventaActualizar->estatus !== 'cancelado') {
                $ventaActualizar->update([
                    'estatus' => 'cancelado',
                    'user_id_cancelacion' => backpack_user()->id,
                    'fecha_cancelacion' => now(),
                    'comentario_cancelacion' => $venta['comentario_cancelacion'] ?? 'N/A'
                ]);
                $ventaActualizar->reservaciones()->update(['estado' => 'cancelada']);
                $ventaActualizar->save();
                foreach ($ventaActualizar->productos as $producto) {
                    (new ExistenciaAction())::existenciaCancelacion($producto->producto_id, $producto->cantidad);
                }
                $ventasCanceladas[] = [
                    'venta_id' => $ventaActualizar->id,
                    'folio' => $ventaActualizar->folio,
                    'estatus' => $ventaActualizar->estatus,
                    'reservaciones' => $ventaActualizar->reservaciones->toArray()
                ];
            }

        }
        return $ventasCanceladas;
    }
}
