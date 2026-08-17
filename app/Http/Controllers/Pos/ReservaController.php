<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

        $reservas = Reserva::query()
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
}
