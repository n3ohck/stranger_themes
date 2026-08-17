<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Descuento;
use App\Models\Producto;

class CatalogoController extends Controller
{
    /**
     * Catálogo completo de la sucursal en una sola llamada.
     *
     * El POS lo carga una vez al abrir caja y opera contra esa copia: la venta
     * tiene que fluir aunque la conexión se ponga intermitente a media tarde.
     * Los precios reales se recalculan en el servidor al cobrar, así que una
     * copia desactualizada nunca puede producir un cobro incorrecto.
     */
    public function index()
    {
        $productos = Producto::query()
            ->orderBy('descripcion')
            ->get();

        // Los paquetes guardan sus tours como [{"producto_id":"2"}, ...].
        // Se resuelven aquí para que el POS sepa cuántas reservaciones pedir.
        $porId = $productos->keyBy('id');

        $catalogo = $productos->map(function (Producto $producto) use ($porId) {
            $tours = collect($producto->tours ?? [])
                ->pluck('producto_id')
                ->map(fn ($id) => (int) $id)
                ->map(function (int $id) use ($porId) {
                    $tour = $porId->get($id);

                    return $tour ? [
                        'id' => $tour->id,
                        'nombre' => $tour->descripcion,
                    ] : null;
                })
                ->filter()
                ->values();

            return [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->descripcion,
                'precio' => (float) $producto->precio,
                'tipo' => $producto->tipo,
                'existencia' => (int) $producto->existencia,
                // Solo los artículos manejan inventario; un tour nunca se agota por stock.
                'controla_existencia' => $producto->tipo === 'articulo',
                'requiere_reservacion' => in_array($producto->tipo, ['tour', 'tour_paquete', 'diferencias'], true),
                'tours' => $tours,
            ];
        });

        $descuentos = Descuento::query()
            ->where('estatus', 'activo')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Descuento $descuento) => [
                'id' => $descuento->id,
                'codigo' => $descuento->codigo,
                'porcentaje' => (float) $descuento->porcentaje,
                'producto_tipo' => $descuento->producto_tipo,
            ]);

        return response()->json([
            'productos' => $catalogo->values(),
            'descuentos' => $descuentos->values(),
        ]);
    }
}
