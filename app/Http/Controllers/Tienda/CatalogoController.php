<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Support\Tienda\DescuentoAplicado;
use App\Support\Tienda\Disponibilidad;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Catálogo público de la tienda. Sin sesión: cualquiera puede consultarlo.
 */
class CatalogoController extends Controller
{
    public function index()
    {
        $sucursales = Sucursal::query()->orderBy('razon_social')->get();

        $productos = Producto::query()
            ->withoutGlobalScopes()
            ->where('visible_en_tienda', true)
            ->orderByRaw("FIELD(tipo,'tour','tour_paquete')")
            ->orderBy('precio')
            ->get();

        return response()->json([
            'sucursales' => $sucursales->map(fn (Sucursal $s) => [
                'id' => $s->id,
                'nombre' => $s->razon_social,
                'direccion' => $s->direccion,
                'telefono' => $s->telefono,
                'ubicacion' => $s->ubicacion,
                'horarios' => $s->horarios,
                // Días que abre, para pintar el calendario sin adivinar.
                'dias_abiertos' => collect($s->horarios ?? [])->pluck('dia')->values(),
            ])->values(),

            'productos' => $productos->map(function (Producto $p) {
                $recorridos = Disponibilidad::recorridosDe($p);

                return [
                    'id' => $p->id,
                    'sucursal_id' => $p->sucursal_id,
                    'nombre' => $p->descripcion,
                    'tipo' => $p->tipo,
                    'precio' => (float) $p->precio,
                    'capacidad' => $p->capacidad,
                    'duracion_minutos' => $p->duracion_minutos,
                    'recorridos' => $recorridos->map(fn (Producto $r) => [
                        'id' => $r->id,
                        'nombre' => $r->descripcion,
                        'duracion_minutos' => $r->duracion_minutos,
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Horarios que se pueden comprar para un producto en una fecha.
     */
    public function disponibilidad(Request $request)
    {
        $datos = $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ], [
            'fecha.date_format' => 'La fecha debe tener formato AAAA-MM-DD.',
        ]);

        $sucursal = Sucursal::findOrFail($datos['sucursal_id']);

        $producto = Producto::query()
            ->withoutGlobalScopes()
            ->where('id', $datos['producto_id'])
            ->where('sucursal_id', $sucursal->id)
            ->where('visible_en_tienda', true)
            ->first();

        if (! $producto) {
            return response()->json(['error' => 'Ese recorrido no está disponible en esa sucursal.'], 404);
        }

        $fecha = Carbon::parse($datos['fecha']);
        $horarios = Disponibilidad::paraProducto($producto, $sucursal, $fecha);

        return response()->json([
            'fecha' => $fecha->toDateString(),
            'abierto' => $horarios !== [] || $this->abreEseDia($sucursal, $fecha),
            // Solo la hora de inicio: los tramos internos del paquete los resuelve
            // el servidor al apartar, para que el navegador no pueda proponerlos.
            'horarios' => collect($horarios)->map(fn ($h) => [
                'inicio' => $h['inicio'],
                'etiqueta' => $h['etiqueta'],
                'termina' => $this->finDe($h),
            ])->values(),
        ]);
    }

    /**
     * Valida un código mientras el cliente lo escribe y devuelve los importes.
     *
     * Los calcula el mismo resolutor que se usa al cobrar, así que lo que ve el
     * cliente aquí es exactamente lo que va a pagar.
     */
    public function descuento(Request $request)
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'personas' => ['required', 'integer', 'min:1', 'max:20'],
            'codigo' => ['required', 'string', 'max:100'],
        ]);

        $producto = Producto::query()
            ->withoutGlobalScopes()
            ->where('id', $datos['producto_id'])
            ->where('visible_en_tienda', true)
            ->first();

        if (! $producto) {
            return response()->json(['valido' => false, 'mensaje' => 'Ese recorrido no está disponible.'], 404);
        }

        try {
            $aplicado = DescuentoAplicado::resolver($datos['codigo'], $producto, (int) $datos['personas']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Se responde 200 con valido:false: para el cliente no es un error del
            // sistema, es un código que no sirve, y la pantalla lo trata distinto.
            return response()->json([
                'valido' => false,
                'mensaje' => $e->validator->errors()->first(),
            ]);
        }

        return response()->json(['valido' => true] + $aplicado->paraJson());
    }

    private function abreEseDia(Sucursal $sucursal, Carbon $fecha): bool
    {
        $dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $hoy = $dias[$fecha->dayOfWeek];

        return collect($sucursal->horarios ?? [])
            ->contains(fn ($h) => strtr(mb_strtolower($h['dia'] ?? ''), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']) === $hoy);
    }

    private function finDe(array $horario): string
    {
        $ultimo = Carbon::parse(end($horario['tramos'])['inicio']);

        return $ultimo->addMinutes(Disponibilidad::INTERVALO_MINUTOS)->format('g:i a');
    }
}
