<?php

namespace App\Http\Controllers\Pos;

use App\Actions\ExistenciaAction;
use App\Actions\Pos\AmpliarReservaAction;
use App\Actions\Pos\RegistrarVentaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\RegistrarVentaRequest;
use App\Models\Apertura;
use App\Models\Reserva;
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
     * que puede cancelar. Las de mostrador se filtran por apertura_id y no por
     * rango de fechas: un turno que cruza la medianoche seguía siendo un turno.
     *
     * Las del sitio web no pasan por caja. Se muestran dos grupos: las compradas
     * desde que se abrió la caja (las mismas que suma el corte) y las que tienen
     * visita hoy aunque se hayan comprado otro día, para recibir a quien llega.
     * Van aparte en el resumen: no son dinero del cajón.
     */
    public function delTurno()
    {
        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['apertura' => null, 'ventas' => [], 'resumen' => $this->resumenVacio()]);
        }

        $ventas = Venta::query()
            ->where('apertura_id', $apertura->id)
            ->with(['productos.producto', 'pagos', 'reservaciones.producto', 'ventaOriginal'])
            ->orderByDesc('id')
            ->get();

        $sucursalId = Auth::user()->sucursal_id;
        $relaciones = ['productos.producto', 'pagos', 'reservaciones.producto', 'ventaOriginal'];

        $enLinea = Venta::query()
            ->enLineaEntre($sucursalId, Carbon::parse($apertura->created_at), Carbon::now())
            ->with($relaciones)
            ->get();

        $visitanHoy = Venta::query()
            ->enLineaConVisitaEntre($sucursalId, Carbon::today(), Carbon::today()->endOfDay())
            ->with($relaciones)
            ->get();

        // Una compra hecha en el turno para hoy cae en los dos grupos.
        $todas = $ventas->toBase()
            ->merge($enLinea->toBase())
            ->merge($visitanHoy->toBase())
            ->unique('id')
            ->sortByDesc(fn (Venta $venta) => (string) $venta->created_at)
            ->values();

        return response()->json([
            'apertura' => [
                'id' => $apertura->id,
                'monto_apertura' => (float) $apertura->monto_apertura,
                'abierta_en' => (string) $apertura->created_at,
            ],
            'ventas' => $todas->map(fn (Venta $venta) => $this->presentar($venta))->values(),
            'resumen' => $this->resumen($ventas) + [
                'en_linea' => round((float) $enLinea->where('estatus', 'activo')->sum('total'), 2),
                'en_linea_cantidad' => $enLinea->where('estatus', 'activo')->count(),
                'visitan_hoy' => $visitanHoy->where('estatus', 'activo')->count(),
            ],
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
     * Cuánto cuesta subir las personas de una reservación, antes de cobrar. El
     * POS lo muestra en la edición y lo usa como total del cobro.
     */
    public function cotizarAmpliacion(Request $request, Reserva $reserva)
    {
        $datos = $request->validate(['personas' => ['required', 'integer', 'min:1', 'max:99']]);

        if ($error = $this->rechazoAmpliacion($reserva)) {
            return $error;
        }

        $cotizacion = (new AmpliarReservaAction(Auth::user()))->cotizar($reserva, (int) $datos['personas']);

        return response()->json([
            'extra' => $cotizacion['extra'],
            'producto' => $cotizacion['producto']->descripcion,
            'total_unitario' => $cotizacion['total_unitario'],
            'descuento_unitario' => $cotizacion['descuento_unitario'],
            'codigo_descuento' => optional($cotizacion['linea'])->codigo_descuento,
            'total' => $cotizacion['total'],
            'recorridos' => $cotizacion['reservas']->map(fn (Reserva $r) => optional($r->producto)->descripcion)->values(),
        ]);
    }

    /**
     * Cobra las personas extra de una reservación como una venta de ampliación
     * del turno y sube la reservación (o todos los recorridos del paquete).
     */
    public function ampliarReserva(Request $request, Reserva $reserva)
    {
        $datos = $request->validate([
            'personas' => ['required', 'integer', 'min:1', 'max:99'],
            'pagos' => ['present', 'array'],
            'pagos.*.tipo' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:255'],
        ]);

        if ($error = $this->rechazoAmpliacion($reserva)) {
            return $error;
        }

        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json([
                'message' => 'No tienes una caja abierta. Realiza la apertura antes de cobrar.',
            ], 409);
        }

        $pagos = collect($datos['pagos'])->filter(fn ($p) => (float) $p['monto'] > 0)->values()->all();

        $venta = DB::transaction(fn () => (new AmpliarReservaAction(Auth::user()))
            ->ampliar($reserva, (int) $datos['personas'], $pagos, $apertura));

        return response()->json([
            'message' => 'Ampliación cobrada.',
            'venta' => $this->presentar($venta),
        ], 201);
    }

    private function rechazoAmpliacion(Reserva $reserva)
    {
        if ((int) $reserva->sucursal_id !== (int) Auth::user()->sucursal_id) {
            return response()->json(['message' => 'Esa reservación no es de tu sucursal.'], 403);
        }

        if ($reserva->estado !== 'confirmada') {
            return response()->json(['message' => 'Solo se pueden ampliar reservaciones confirmadas.'], 422);
        }

        if ($reserva->venta && $reserva->venta->estatus === 'cancelado') {
            return response()->json(['message' => 'La venta de esta reservación está cancelada.'], 422);
        }

        return null;
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
        $venta->load(['productos.producto', 'pagos', 'reservaciones.producto', 'sucursal', 'user', 'ventaOriginal']);

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
                'ampliacion_de' => optional($venta->ventaOriginal)->folio,
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
            // Si es el cobro de personas agregadas a una reservación ya vendida.
            'ampliacion_de' => optional($venta->ventaOriginal)->folio,
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
            'en_linea' => 0, 'en_linea_cantidad' => 0, 'visitan_hoy' => 0,
        ];
    }
}
