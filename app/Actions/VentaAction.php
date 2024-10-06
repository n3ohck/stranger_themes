<?php
namespace App\Actions;

use App\Models\Venta;

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
            $nuevaVenta = Venta::create([
                'user_id' => $userId,
                'descuento_id' => $venta['descuento_id'] ?? null,
                'sucursal_id' => $sucursalId,
                'folio' => $this->makeFolio(),
                'total' => $venta['total'],
                'codigo_descuento' => $venta['codigo_descuento'] ?? null,
                'descuento' => $venta['descuento'],
                'porcentaje_descuento' => $venta['porcentaje_descuento'] ?? null
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
}
