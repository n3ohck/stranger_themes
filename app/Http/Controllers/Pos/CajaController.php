<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Apertura;
use App\Models\Corte;
use App\Models\Egreso;
use App\Models\EmpleadoPago;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function abrir(Request $request)
    {
        $request->validate([
            'monto_apertura' => ['required', 'numeric', 'min:0'],
        ], [
            'monto_apertura.required' => 'Indica el fondo con el que abres la caja.',
            'monto_apertura.numeric' => 'El fondo de apertura debe ser un número.',
            'monto_apertura.min' => 'El fondo de apertura no puede ser negativo.',
        ]);

        $user = Auth::user();

        if (Apertura::aperturaActiva($user)) {
            return response()->json([
                'message' => 'Ya tienes una caja abierta. Realiza el corte antes de abrir otra.',
            ], 409);
        }

        $apertura = Apertura::create([
            'user_id' => $user->id,
            'sucursal_id' => $user->sucursal_id,
            'monto_apertura' => $request->input('monto_apertura'),
            'estado' => 'abierto',
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Caja abierta.',
            'apertura' => [
                'id' => $apertura->id,
                'monto_apertura' => (float) $apertura->monto_apertura,
                'abierta_en' => (string) $apertura->created_at,
                'estado' => $apertura->estado,
            ],
        ], 201);
    }

    /**
     * Totales del turno calculados por el servidor.
     *
     * En el flujo anterior el POS mandaba estos importes ya sumados y el backend
     * los guardaba sin verificar: si el cliente sumaba mal, el corte quedaba mal
     * y nadie se enteraba. Ahora el POS solo declara el efectivo contado en caja
     * y el servidor calcula todo lo demás.
     */
    public function precorte()
    {
        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        return response()->json(['precorte' => $this->calcularPrecorte($apertura)]);
    }

    public function cerrar(Request $request)
    {
        $request->validate([
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'fondo_final' => ['nullable', 'numeric', 'min:0'],
        ], [
            'efectivo_contado.required' => 'Indica cuánto efectivo hay físicamente en la caja.',
        ]);

        $user = Auth::user();
        $apertura = Apertura::aperturaActiva($user);

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        $precorte = $this->calcularPrecorte($apertura);

        $corte = DB::transaction(function () use ($request, $apertura, $precorte, $user) {
            $corte = Corte::create([
                'total' => $precorte['ventas']['total'],
                'efectivo' => $precorte['ventas']['efectivo'],
                'tarjeta' => $precorte['ventas']['tarjeta'],
                'transferencia' => $precorte['ventas']['transferencia'],
                'total_online' => $precorte['ventas']['online'],
                'total_caja' => $request->input('efectivo_contado'),
                'fondo_final' => $request->input('fondo_final') ?? 0,
                'fecha_inicio' => $apertura->created_at,
                'fecha_final' => Carbon::now(),
                'user_id' => $user->id,
                'sucursal_id' => $user->sucursal_id,
                'apertura_id' => $apertura->id,
            ]);

            $apertura->estado = 'cerrado';
            $apertura->user_id_cerro = $user->id;
            $apertura->monto_cierre = $corte->total_caja;
            $apertura->save();

            return $corte;
        });

        return response()->json([
            'message' => 'Corte realizado.',
            'corte' => $this->presentarCorte($corte, $precorte),
        ], 201);
    }

    public function ticketCorte(Corte $corte)
    {
        $apertura = Apertura::find($corte->apertura_id);
        $precorte = $apertura ? $this->calcularPrecorte($apertura, $corte->fecha_final) : null;

        return response()->json(['corte' => $this->presentarCorte($corte, $precorte)]);
    }

    /**
     * @param  Carbon|null  $hasta  Permite recalcular el corte de un turno ya cerrado.
     */
    private function calcularPrecorte(Apertura $apertura, $hasta = null): array
    {
        $user = Auth::user();
        $desde = Carbon::parse($apertura->created_at);
        $hasta = $hasta ? Carbon::parse($hasta) : Carbon::now();

        $ventas = Venta::query()
            ->where('apertura_id', $apertura->id)
            ->with('pagos')
            ->get();

        $activas = $ventas->where('estatus', 'activo');
        $pagos = $activas->pluck('pagos')->flatten();

        // El cambio entregado sale del cajón, así que el efectivo neto del turno
        // es lo recibido menos lo devuelto.
        $efectivoRecibido = (float) $pagos->where('tipo', 'efectivo')->sum('monto');
        $cambioEntregado = (float) $pagos->sum('cambio');
        $efectivoNeto = $efectivoRecibido - $cambioEntregado;

        // Las ventas del sitio web no pasan por este cajón, pero se reportan en
        // el corte porque el negocio las revisa junto con las del turno.
        $online = (float) Venta::query()
            ->withoutGlobalScopes()
            ->where('sucursal_id', $user->sucursal_id)
            ->where('origen', 'web')
            ->where('estatus', 'activo')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('total');

        // Los movimientos capturados en el POS traen apertura_id y cuentan en su
        // turno sin importar la fecha declarada. Los capturados desde el panel no
        // pertenecen a ninguna caja, así que se siguen contando por ventana.
        $egresos = Egreso::query()
            ->where('sucursal_id', $user->sucursal_id)
            ->where('estatus', 'activo')
            ->where(function ($query) use ($apertura, $desde, $hasta) {
                $query->where('apertura_id', $apertura->id)
                    ->orWhere(fn ($sinCaja) => $sinCaja
                        ->whereNull('apertura_id')
                        ->whereBetween('fecha_pago', [$desde, $hasta]));
            })
            ->get();

        $egresosEfectivo = (float) $egresos->where('tipo_pago', 'efectivo')->sum('monto');
        $egresosTotal = (float) $egresos->sum('monto');

        $pagosEmpleados = (float) EmpleadoPago::query()
            ->where('estatus', 'activo')
            ->where(function ($query) use ($apertura, $desde, $hasta) {
                $query->where('apertura_id', $apertura->id)
                    ->orWhere(fn ($sinCaja) => $sinCaja
                        ->whereNull('apertura_id')
                        ->whereBetween('created_at', [$desde, $hasta]));
            })
            ->sum('monto');

        $fondoInicial = (float) $apertura->monto_apertura;
        $efectivoEsperado = $fondoInicial + $efectivoNeto - $egresosEfectivo - $pagosEmpleados;

        return [
            'apertura' => [
                'id' => $apertura->id,
                'fondo_inicial' => $fondoInicial,
                'abierta_en' => (string) $apertura->created_at,
            ],
            'periodo' => [
                'desde' => $desde->toDateTimeString(),
                'hasta' => $hasta->toDateTimeString(),
            ],
            'ventas' => [
                'cantidad' => $activas->count(),
                'canceladas' => $ventas->where('estatus', 'cancelado')->count(),
                'total' => round((float) $activas->sum('total'), 2),
                'descuentos' => round((float) $activas->sum('descuento'), 2),
                'efectivo' => round($efectivoNeto, 2),
                'efectivo_recibido' => round($efectivoRecibido, 2),
                'cambio_entregado' => round($cambioEntregado, 2),
                'tarjeta' => round((float) $pagos->where('tipo', 'tarjeta')->sum('monto'), 2),
                'transferencia' => round((float) $pagos->where('tipo', 'transferencia')->sum('monto'), 2),
                'online' => round($online, 2),
            ],
            'salidas' => [
                'egresos_efectivo' => round($egresosEfectivo, 2),
                'egresos_total' => round($egresosTotal, 2),
                'pagos_empleados' => round($pagosEmpleados, 2),
            ],
            'caja' => [
                'efectivo_esperado' => round($efectivoEsperado, 2),
            ],
        ];
    }

    private function presentarCorte(Corte $corte, ?array $precorte): array
    {
        $contado = (float) $corte->total_caja;
        $esperado = $precorte['caja']['efectivo_esperado'] ?? null;

        return [
            'id' => $corte->id,
            'fecha_inicio' => (string) $corte->fecha_inicio,
            'fecha_final' => (string) $corte->fecha_final,
            'total' => (float) $corte->total,
            'efectivo' => (float) $corte->efectivo,
            'tarjeta' => (float) $corte->tarjeta,
            'transferencia' => (float) $corte->transferencia,
            'online' => (float) $corte->total_online,
            'efectivo_contado' => $contado,
            'fondo_final' => (float) $corte->fondo_final,
            'efectivo_esperado' => $esperado,
            'diferencia' => $esperado === null ? null : round($contado - $esperado, 2),
            'detalle' => $precorte,
        ];
    }
}
