<?php
namespace App\Actions;

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
    public function makeFolio():string{
        $lastVenta = Venta::query()
            ->select(['id','created_at'])
            ->latest()
            ->first();
        if(!$lastVenta) return 'VTA-1';
        return 'VTA-'.($lastVenta->id + 1);
    }
    public function do(array $ventas):array
    {
        $nuevasVentas = [];
        foreach ($ventas as $venta){
            $venta['datetime'] = $this->makeDate($venta['datetime']);
            $existe = Venta::query()
                ->where('created_at', $venta['datetime'])
                ->where('total', $venta['total'])
                ->exists();
            if( $existe ) continue;
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

            if( isset($venta['reservaciones']) ){
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

    public function makeVentaReservacion(int $ventaId, array $reservas):array
    {
        $reservasNuevas = [];
        foreach ($reservas as $reserva){
            $reserva['datetime'] = $this->makeDate($reserva['datetime']);
            $reservasNuevas[] = Reserva::create([
                'producto_id' => $reserva['producto_id'],
                'nombre_cliente' => $reserva['name'],
                'cantidad_personas' => $reserva['number'],
                'fecha' => $reserva['datetime'],
                'estado' => 'confirmada',
                'sucursal_id' => $this->user->sucursal_id,
                'venta_id' => $ventaId
            ]);
        }
        return $reservasNuevas;
    }

    public function makeVentaProductos(int $ventaId, array $productos):void
    {
        $totalDescuento = 0;
        $subtotal = 0;
        foreach ($productos as $producto){
            $subtotal+=$producto['precio'];
            $descuentos = !empty($producto['descuentos']) ? $producto['descuentos'][0] : null;
            $descuento = $descuentos && isset($descuentos['descuento']) ? $descuentos['descuento'] : 0;

            if ($descuento) {
                $totalDescuento += $producto['precio'] - $producto['total'];
            }

            VentaProducto::create([
                'venta_id' => $ventaId,
                'producto_id' => $producto['producto_id'],
                'precio' => $producto['precio'],
                'cantidad' => $producto['cantidad'],
                'total' => $producto['total'],
                'descuento_id' => $descuentos['descuento_id'] ?? null,
                'codigo_descuento' => $descuentos['codigo_descuento'] ?? null,
                'descuento' => ($descuento) ? number_format($producto['precio'] - $producto['total'],2,'.','') : 0,
                'porcentaje_descuento' => $descuentos['porcentaje_descuento'] ?? 0
            ]);
            (new ExistenciaAction())::salidarPorVenta($producto['producto_id'], $producto['cantidad']);
        }
        $venta = Venta::find($ventaId);
        $venta->update([
            'descuento' => $totalDescuento,
            'porcentaje_descuento' => ($subtotal) ? number_format(($totalDescuento / $subtotal) * 100,2,'.','') : 0
        ]);
    }

    public function makeVentaPagos(int $ventaId, array $pagos):void
    {
        foreach ($pagos as $pago){
            VentaPago::create([
                'venta_id' => $ventaId,
                'monto' => $pago['monto'],
                'cambio' => $pago['cambio'] ?? 0,
                'tipo' => $pago['tipo'],
                'referencia' => $pago['referencia'] ?? null
            ]);
        }
    }

    public function cancelVentas(array $ventas):array
    {
        $ventasCanceladas = [];
        foreach ($ventas as $venta){
            $ventaActualizar = Venta::find($venta['venta_id']);
            if(!$ventaActualizar) throw new \Exception('No se ha encontrado la venta a cancelar');
            if($ventaActualizar->estatus !== 'cancelado'){
                $ventaActualizar->update([
                    'estatus' => 'cancelado',
                    'user_id_cancelacion' => backpack_user()->id,
                    'fecha_cancelacion' => now(),
                    'comentario_cancelacion' => $venta['comentario_cancelacion'] ?? 'N/A'
                ]);
                $ventaActualizar->reservaciones()->update(['estado' => 'cancelada']);
                $ventaActualizar->save();
                foreach ($ventaActualizar->productos as $producto){
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
