<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Support\Tienda\Disponibilidad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Horarios que el cajero puede vender.
 *
 * Sale del mismo motor que la tienda en línea y la lista de reservas, así que los
 * tres muestran exactamente lo mismo: lo que se configura en el horario de la
 * sucursal es lo único que define cuándo sale un recorrido.
 */
class DisponibilidadController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ], [
            'fecha.date_format' => 'La fecha debe tener formato AAAA-MM-DD.',
        ]);

        $sucursal = Sucursal::findOrFail(Auth::user()->sucursal_id);
        $producto = $this->productoDe($sucursal, $datos['producto_id']);

        if (! $producto) {
            return response()->json(['error' => 'Ese producto no es de tu sucursal.'], 404);
        }

        $fecha = Carbon::parse($datos['fecha']);

        // Sin anticipación: en mostrador se vende la salida que viene enseguida.
        $libres = Disponibilidad::paraProducto($producto, $sucursal, $fecha, 0);
        $rejilla = Disponibilidad::rejillaDelDia($sucursal, $fecha);

        $porInicio = collect($libres)->keyBy('inicio');

        return response()->json([
            'fecha' => $fecha->toDateString(),
            'intervalo_minutos' => Disponibilidad::intervaloDe($sucursal, $fecha),
            'abre' => $rejilla !== [],
            // Se devuelve la rejilla completa con su estado: el cajero necesita ver
            // los horarios ocupados para explicarle al cliente por qué no puede.
            'horarios' => collect($rejilla)->map(fn (string $hora) => [
                'inicio' => $hora,
                'etiqueta' => Carbon::parse($hora)->format('g:i a'),
                'libre' => $porInicio->has($hora),
                'tramos' => $porInicio->get($hora)['tramos'] ?? [],
            ])->values(),
        ]);
    }

    /**
     * Resumen por día para el calendario de tarjetas: si la sucursal abre y
     * cuántos horarios quedan libres. Así el cajero ve de un vistazo qué días
     * están cerrados o llenos sin abrir uno por uno.
     */
    public function dias(Request $request)
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:31'],
        ], [
            'desde.date_format' => 'La fecha debe tener formato AAAA-MM-DD.',
        ]);

        $sucursal = Sucursal::findOrFail(Auth::user()->sucursal_id);
        $producto = $this->productoDe($sucursal, $datos['producto_id']);

        if (! $producto) {
            return response()->json(['error' => 'Ese producto no es de tu sucursal.'], 404);
        }

        $desde = Carbon::parse($datos['desde'])->startOfDay();

        $dias = collect(range(0, ($datos['dias'] ?? 14) - 1))->map(function (int $i) use ($desde, $producto, $sucursal) {
            $fecha = $desde->copy()->addDays($i);
            $rejilla = Disponibilidad::rejillaDelDia($sucursal, $fecha);

            return [
                'fecha' => $fecha->toDateString(),
                'abre' => $rejilla !== [],
                // Misma anticipación 0 que index(): lo que se ve aquí es lo que
                // luego aparece al abrir el día.
                'libres' => $rejilla === [] ? 0 : count(Disponibilidad::paraProducto($producto, $sucursal, $fecha, 0)),
            ];
        });

        return response()->json(['dias' => $dias->values()]);
    }

    private function productoDe(Sucursal $sucursal, int $productoId): ?Producto
    {
        return Producto::query()
            ->where('id', $productoId)
            ->where('sucursal_id', $sucursal->id)
            ->first();
    }
}
