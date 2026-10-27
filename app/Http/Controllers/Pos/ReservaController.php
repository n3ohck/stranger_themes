<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Support\Tienda\Disponibilidad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservaController extends Controller
{
    /**
     * Reservaciones para el calendario, agrupadas por día.
     *
     * Trae la venta para poder decir de dónde salió cada reservación: el cajero
     * necesita distinguir una reserva pagada en el sitio web de una hecha en
     * mostrador, porque el trato al cliente que llega es distinto.
     */
    public function index(Request $request)
    {
        $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado' => ['nullable', 'in:confirmada,cancelada'],
        ], [
            'desde.required' => 'Indica la fecha inicial del calendario.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        $desde = Carbon::parse($request->input('desde'))->startOfDay();
        $hasta = Carbon::parse($request->input('hasta'))->endOfDay();

        // Filtro explícito de sucursal por la misma razón que en el catálogo: el
        // aislamiento del POS no debe depender del scope ambiental.
        $reservas = Reserva::query()
            ->where('sucursal_id', Auth::user()->sucursal_id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when(
                $request->filled('estado'),
                fn ($query) => $query->where('estado', $request->input('estado')),
                fn ($query) => $query->where('estado', 'confirmada')
            )
            ->with(['producto', 'venta.pagos'])
            ->orderBy('fecha')
            ->get();

        $items = $reservas->map(function (Reserva $reserva) {
            $venta = $reserva->venta;
            $fecha = Carbon::parse($reserva->fecha);

            return [
                'id' => $reserva->id,
                'fecha' => $fecha->toDateString(),
                'hora' => $fecha->format('H:i'),
                'fecha_hora' => $fecha->toDateTimeString(),
                'producto' => optional($reserva->producto)->descripcion,
                'producto_id' => $reserva->producto_id,
                'cliente' => $reserva->nombre_cliente,
                'personas' => (int) $reserva->cantidad_personas,
                'estado' => $reserva->estado,
                'origen' => $venta->origen ?? 'pos',
                'venta' => $venta ? [
                    'id' => $venta->id,
                    'folio' => $venta->folio,
                    'total' => (float) $venta->total,
                    'estatus' => $venta->estatus,
                    'telefono' => $venta->telefono,
                    'email' => $venta->email,
                    'pagada' => $venta->pagos->isNotEmpty(),
                    'formas_pago' => $venta->pagos->pluck('tipo')->unique()->values(),
                ] : null,
            ];
        });

        $porDia = $items->groupBy('fecha')->map(fn ($delDia, $dia) => [
            'fecha' => $dia,
            'reservaciones' => $delDia->sortBy('hora')->values(),
            'total_reservaciones' => $delDia->count(),
            'total_personas' => $delDia->sum('personas'),
            'desde_web' => $delDia->where('origen', 'web')->count(),
            'desde_pos' => $delDia->where('origen', 'pos')->count(),
        ])->values()->sortBy('fecha')->values();

        return response()->json([
            'dias' => $porDia,
            'totales' => [
                'reservaciones' => $items->count(),
                'personas' => $items->sum('personas'),
                'desde_web' => $items->where('origen', 'web')->count(),
                'desde_pos' => $items->where('origen', 'pos')->count(),
            ],
        ]);
    }

    /**
     * Agenda de un día completo, hora por hora.
     *
     * Devuelve TODOS los horarios en que la sucursal puede sacar un recorrido, no
     * solo los que tienen reservación. Quien atiende necesita ver los huecos tanto
     * como lo vendido: es lo que le permite ofrecer un lugar por teléfono o mover
     * un grupo que llegó tarde.
     */
    public function dia(Request $request)
    {
        $datos = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $sucursal = Sucursal::findOrFail(Auth::user()->sucursal_id);
        $fecha = Carbon::parse($datos['fecha'] ?? Carbon::now()->toDateString());

        $reservas = Reserva::query()
            ->where('sucursal_id', $sucursal->id)
            ->where('estado', 'confirmada')
            ->whereBetween('fecha', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->with(['producto', 'venta.pagos'])
            ->orderBy('fecha')
            ->get();

        $porHora = $reservas->groupBy(fn (Reserva $r) => Carbon::parse($r->fecha)->format('H:i'));
        $rejilla = Disponibilidad::rejillaDelDia($sucursal, $fecha);

        // Una reservación puede caer fuera de la rejilla si el horario de la sucursal
        // cambió después de venderla. Se muestra igual: existe y hay que atenderla.
        $fueraDeRejilla = $porHora->keys()->diff($rejilla)->values();
        $horas = collect($rejilla)->merge($fueraDeRejilla)->unique()->sort()->values();

        return response()->json([
            'fecha' => $fecha->toDateString(),
            'abre' => $rejilla !== [],
            'intervalo_minutos' => Disponibilidad::intervaloDe($sucursal, $fecha),
            'horarios' => $horas->map(fn (string $hora) => [
                'inicio' => $hora,
                'etiqueta' => Carbon::parse($hora)->format('g:i a'),
                'en_rejilla' => in_array($hora, $rejilla, true),
                'reservaciones' => ($porHora->get($hora) ?? collect())
                    ->map(fn (Reserva $r) => $this->presentar($r))->values(),
                // Un horario se comparte entre grupos: cuántas personas lleva cada
                // recorrido contra su cupo, para ver si se juntaron o se pasaron.
                'ocupacion' => ($porHora->get($hora) ?? collect())
                    ->groupBy('producto_id')
                    ->map(fn ($grupos) => [
                        'producto' => optional($grupos->first()->producto)->descripcion,
                        'grupos' => $grupos->count(),
                        'personas' => (int) $grupos->sum('cantidad_personas'),
                        'capacidad' => optional($grupos->first()->producto)->capacidad
                            ? (int) $grupos->first()->producto->capacidad
                            : null,
                    ])->values(),
            ])->values(),
            'totales' => [
                'reservaciones' => $reservas->count(),
                'personas' => (int) $reservas->sum('cantidad_personas'),
                'desde_web' => $reservas->filter(fn ($r) => optional($r->venta)->origen === 'web')->count(),
                'desde_pos' => $reservas->filter(fn ($r) => optional($r->venta)->origen !== 'web')->count(),
            ],
        ]);
    }

    /**
     * Mueve o ajusta una reservación.
     *
     * Por decisión del negocio la venta no se toca: si un grupo de 4 entra con 6, la
     * reservación lo refleja pero la venta sigue diciendo lo que se cobró. Es
     * deliberado para no descuadrar el corte, y significa que los reportes de dinero
     * no reflejan estos ajustes.
     */
    public function actualizar(Request $request, Reserva $reserva)
    {
        $sucursalId = Auth::user()->sucursal_id;

        if ((int) $reserva->sucursal_id !== (int) $sucursalId) {
            return response()->json(['message' => 'Esa reservación no es de tu sucursal.'], 403);
        }

        if ($reserva->estado !== 'confirmada') {
            return response()->json(['message' => 'Solo se pueden ajustar reservaciones confirmadas.'], 422);
        }

        $datos = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'hora' => ['nullable', 'date_format:H:i'],
            'personas' => ['nullable', 'integer', 'min:1'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'nombre_cliente' => ['nullable', 'string', 'max:255'],
        ], [
            'hora.date_format' => 'La hora debe tener formato HH:MM.',
        ]);

        $productoId = $datos['producto_id'] ?? $reserva->producto_id;

        $producto = Producto::query()
            ->where('id', $productoId)
            ->where('sucursal_id', $sucursalId)
            ->first();

        if (! $producto) {
            return response()->json(['message' => 'Ese recorrido no es de tu sucursal.'], 422);
        }

        $actual = Carbon::parse($reserva->fecha);

        $destino = Carbon::parse(
            ($datos['fecha'] ?? $actual->toDateString()) . ' ' . ($datos['hora'] ?? $actual->format('H:i'))
        );

        // Sin tope de cupo ni de grupos: en mostrador un horario se comparte entre
        // grupos y se puede pasar de la capacidad cuando la gente sí cabe. El cupo
        // solo lo respeta el sitio web.
        $personas = $datos['personas'] ?? (int) $reserva->cantidad_personas;

        $reserva->update([
            'producto_id' => $producto->id,
            'fecha' => $destino,
            'cantidad_personas' => $personas,
            'nombre_cliente' => $datos['nombre_cliente'] ?? $reserva->nombre_cliente,
        ]);

        return response()->json([
            'message' => 'Reservación actualizada.',
            'reservacion' => $this->presentar($reserva->fresh(['producto', 'venta.pagos'])),
        ]);
    }

    private function presentar(Reserva $reserva): array
    {
        $venta = $reserva->venta;
        $fecha = Carbon::parse($reserva->fecha);

        return [
            'id' => $reserva->id,
            'fecha' => $fecha->toDateString(),
            'hora' => $fecha->format('H:i'),
            'fecha_hora' => $fecha->toDateTimeString(),
            'producto' => optional($reserva->producto)->descripcion,
            'producto_id' => $reserva->producto_id,
            'cliente' => $reserva->nombre_cliente,
            'personas' => (int) $reserva->cantidad_personas,
            'estado' => $reserva->estado,
            'origen' => $venta->origen ?? 'pos',
            'venta' => $venta ? [
                'id' => $venta->id,
                'folio' => $venta->folio,
                'total' => (float) $venta->total,
                'estatus' => $venta->estatus,
                'telefono' => $venta->telefono,
                'email' => $venta->email,
                'formas_pago' => $venta->pagos->pluck('tipo')->unique()->values(),
            ] : null,
        ];
    }
}
