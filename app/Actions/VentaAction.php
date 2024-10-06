<?php
namespace App\Actions;

use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VentaAction
{
    public function makeFolio():string{
        $lastVenta = Venta::query()->select(['id','created_at'])->latest()->first();
        if(!$lastVenta) return 'VTA-1';
        return 'VTA-'.($lastVenta->id + 1);
    }
    public function do(array $ventas):array
    {
        $userId = backpack_user()->id;
        $sucursalId = backpack_user()->sucursal_id;
        $nuevasVentas = [];
        foreach ($ventas as $venta){
            $existe = Venta::query()
                ->where('created_at', $venta['datetime'])
                ->where('total', $venta['total'])
                ->exists();
            if( $existe ) continue;
            $nuevaVenta = Venta::create([
                'user_id' => $userId,
                'descuento_id' => $venta['descuento_id'] ?? null,
                'sucursal_id' => $sucursalId,
                'folio' => $this->makeFolio(),
                'total' => $venta['total'],
                'codigo_descuento' => $venta['codigo_descuento'] ?? null,
                'descuento' => $venta['descuento'],
                'porcentaje_descuento' => $venta['porcentaje_descuento'] ?? null,
                'created_at' => Carbon::parse($venta['datetime'])
            ]);
            $this->makeVentaProductos($nuevaVenta->id, $venta['productos']);
            $this->makeVentaPagos($nuevaVenta->id, $venta['pagos']);
            $nuevasVentas[] = [
                'venta_id' => $nuevaVenta->id,
                'folio' => $nuevaVenta->folio,
                'total' => $nuevaVenta->total
            ];
        }
        return $nuevasVentas;
    }

    public function makeVentaProductos(int $ventaId, array $productos):void
    {
        foreach ($productos as $producto){
            VentaProducto::create([
                'venta_id' => $ventaId,
                'producto_id' => $producto['producto_id'],
                'precio' => $producto['precio'],
                'cantidad' => $producto['cantidad'],
                'total' => $producto['total']
            ]);
            (new ExistenciaAction())::salidarPorVenta($producto['producto_id'], $producto['cantidad']);
        }
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
                $ventaActualizar->save();
                foreach ($ventaActualizar->productos as $producto){
                    (new ExistenciaAction())::existenciaCancelacion($producto->producto_id, $producto->cantidad);
                }
                $ventasCanceladas[] = [
                    'venta_id' => $ventaActualizar->id,
                    'folio' => $ventaActualizar->folio,
                    'estatus' => $ventaActualizar->estatus
                ];
            }

        }
        return $ventasCanceladas;
    }
}
