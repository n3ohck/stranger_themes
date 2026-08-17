<?php

namespace App\Http\Controllers\Pos;

use App\Actions\ExistenciaAction;
use App\Actions\Pos\RegistrarVentaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\RegistrarVentaRequest;
use App\Models\Apertura;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    /**
     * Ventas del turno abierto, que es lo que el cajero necesita ver y lo único
     * que puede cancelar. Se filtra por apertura_id y no por rango de fechas:
     * un turno que cruza la medianoche seguía siendo un turno.
     */
    public function delTurno()
    {
        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['apertura' => null, 'ventas' => [], 'resumen' => $this->resumenVacio()]);
        }

        $ventas = Venta::query()
            ->where('apertura_id', $apertura->id)
            ->with(['productos.producto', 'pagos', 'reservaciones.producto'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'apertura' => [
                'id' => $apertura->id,
                'monto_apertura' => (float) $apertura->monto_apertura,
                'abierta_en' => (string) $apertura->created_at,
            ],
            'ventas' => $ventas->map(fn (Venta $venta) => $this->presentar($venta))->values(),
            'resumen' => $this->resumen($ventas),
        ]);
    }

    public function store(RegistrarVentaRequest $request)
    {
        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json([
                'message' => 'No tienes una caja abierta. Realiza la apertura antes de vender.',
            ], 409);
        }

        $venta = DB::transaction(function () use ($request, $apertura) {
            return (new RegistrarVentaAction(Auth::user()))->ejecutar($request->validated(), $apertura);
        });

        return response()->json([
            'message' => 'Venta registrada.',
            'venta' => $this->presentar($venta),
        ], 201);
    }

    /**
     * Cancelar devuelve existencias y cancela las reservaciones asociadas, para
     * que el horario vuelva a quedar libre en el calendario.
     */
    public function cancelar(Request $request, Venta $venta)
    {
        $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'motivo.required' => 'Indica el motivo de la cancelación.',
            'motivo.min' => 'El motivo es demasiado corto.',
        ]);

        if ($venta->estatus === 'cancelado') {
            throw ValidationException::withMessages(['venta' => 'Esta venta ya estaba cancelada.']);
        }

        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura || $venta->apertura_id !== $apertura->id) {
            return response()->json([
                'message' => 'Solo puedes cancelar ventas del turno abierto. Pide a un administrador que cancele esta venta.',
            ], 403);
        }

        DB::transaction(function () use ($venta) {
            $venta->update([
                'estatus' => 'cancelado',
                'user_id_cancelacion' => Auth::id(),
                'fecha_cancelacion' => Carbon::now(),
                'comentario_cancelacion' => request('motivo'),
            ]);

            $venta->reservaciones()->update(['estado' => 'cancelada']);

            foreach ($venta->productos as $linea) {
                ExistenciaAction::existenciaCancelacion($linea->producto_id, (int) $linea->cantidad);
            }
        });

        return response()->json([
            'message' => 'Venta cancelada.',
            'venta' => $this->presentar($venta->fresh(['productos.producto', 'pagos', 'reservaciones.producto'])),
        ]);
    }

    /**
     * Datos del ticket. Se arman en el servidor para que la reimpresión de una
     * venta vieja no dependa de lo que el POS tenga en memoria.
     */
    public function ticket(Venta $venta)
    {
        $venta->load(['productos.producto', 'pagos', 'reservaciones.producto', 'sucursal', 'user']);

        return response()->json([
            'ticket' => [
                'folio' => $venta->folio,
                'fecha' => (string) $venta->created_at,
                'estatus' => $venta->estatus,
                'sucursal' => [
                    'nombre' => optional($venta->sucursal)->razon_social,
                    'direccion' => optional($venta->sucursal)->direccion ?? null,
                    'telefono' => optional($venta->sucursal)->telefono ?? null,
                ],
                'atendio' => optional($venta->user)->nombre_completo,
                'cliente' => $venta->nombre,
                'lineas' => $venta->productos->map(fn ($linea) => [
                    'producto' => optional($linea->producto)->descripcion,
                    'cantidad' => (float) $linea->cantidad,
                    'precio' => (float) $linea->precio,
                    'descuento' => (float) $linea->descuento,
                    'codigo_descuento' => $linea->codigo_descuento,
                    'total' => (float) $linea->total,
                ])->values(),
                'reservaciones' => $venta->reservaciones->map(fn ($reserva) => [
                    'producto' => optional($reserva->producto)->descripcion,
                    'nombre' => $reserva->nombre_cliente,
                    'personas' => (int) $reserva->cantidad_personas,
                    'fecha' => (string) $reserva->fecha,
                    'estado' => $reserva->estado,
                ])->values(),
                'pagos' => $venta->pagos->map(fn ($pago) => [
                    'tipo' => $pago->tipo,
                    'monto' => (float) $pago->monto,
                    'cambio' => (float) $pago->cambio,
                    'referencia' => $pago->referencia,
                ])->values(),
                'subtotal' => (float) $venta->total + (float) $venta->descuento,
                'descuento' => (float) $venta->descuento,
                'total' => (float) $venta->total,
                'cambio' => (float) $venta->pagos->sum('cambio'),
            ],
        ]);
    }

    private function presentar(Venta $venta): array
    {
        return [
            'id' => $venta->id,
            'folio' => $venta->folio,
            'fecha' => (string) $venta->created_at,
            'estatus' => $venta->estatus,
            'origen' => $venta->origen,
            'cliente' => $venta->nombre,
            'total' => (float) $venta->total,
            'descuento' => (float) $venta->descuento,
            'codigo_descuento' => $venta->codigo_descuento,
            'motivo_cancelacion' => $venta->comentario_cancelacion,
            'lineas' => $venta->productos->map(fn ($linea) => [
                'producto' => optional($linea->producto)->descripcion,
                'tipo' => optional($linea->producto)->tipo,
                'cantidad' => (float) $linea->cantidad,
                'precio' => (float) $linea->precio,
                'descuento' => (float) $linea->descuento,
                'total' => (float) $linea->total,
            ])->values(),
            'pagos' => $venta->pagos->map(fn ($pago) => [
                'tipo' => $pago->tipo,
                'monto' => (float) $pago->monto,
                'cambio' => (float) $pago->cambio,
            ])->values(),
            'reservaciones' => $venta->reservaciones->map(fn ($reserva) => [
                'id' => $reserva->id,
                'producto' => optional($reserva->producto)->descripcion,
                'nombre' => $reserva->nombre_cliente,
                'personas' => (int) $reserva->cantidad_personas,
                'fecha' => (string) $reserva->fecha,
                'estado' => $reserva->estado,
            ])->values(),
        ];
    }

    private function resumen($ventas): array
    {
        $activas = $ventas->where('estatus', 'activo');
        $pagos = $activas->pluck('pagos')->flatten();

        return [
            'cantidad' => $activas->count(),
            'canceladas' => $ventas->where('estatus', 'cancelado')->count(),
            'total' => round((float) $activas->sum('total'), 2),
            'efectivo' => round((float) $pagos->where('tipo', 'efectivo')->sum('monto') - (float) $pagos->sum('cambio'), 2),
            'tarjeta' => round((float) $pagos->where('tipo', 'tarjeta')->sum('monto'), 2),
            'transferencia' => round((float) $pagos->where('tipo', 'transferencia')->sum('monto'), 2),
            'descuentos' => round((float) $activas->sum('descuento'), 2),
        ];
    }

    private function resumenVacio(): array
    {
        return [
            'cantidad' => 0, 'canceladas' => 0, 'total' => 0,
            'efectivo' => 0, 'tarjeta' => 0, 'transferencia' => 0, 'descuentos' => 0,
        ];
    }
}
