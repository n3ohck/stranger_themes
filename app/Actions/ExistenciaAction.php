<?php
namespace App\Actions;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use Illuminate\Support\Facades\DB;

class ExistenciaAction
{
    public static function salidarPorVenta(int $productoId, int $cantidad):void
    {
        $producto = Producto::where('id', $productoId)->first();
        $producto->existencia -= $cantidad;
        $producto->save();
    }

    public static function existenciaCancelacion(int $productoId, int $cantidad):void
    {
        $producto = Producto::where('id', $productoId)->first();
        $producto->existencia += $cantidad;
        $producto->save();
    }
}
