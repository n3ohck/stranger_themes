<?php

namespace App\Support\Caja;

use App\Models\Apertura;
use App\Models\Corte;
use App\Scopes\SucursalFilterScope;
use App\Models\Egreso;
use App\Models\EmpleadoPago;
use App\Models\Venta;
use Carbon\Carbon;

/**
 * Números de un turno de caja: el precorte del POS, el ticket de corte y el
 * listado de cortes del panel salen de aquí, para que muestren lo mismo.
 *
 * Antes el panel calculaba por su cuenta: sumaba los pagos a empleados de todas
 * las sucursales, tomaba días completos en lugar del turno y dejaba fuera las
 * ventas en línea de la ganancia, así que no coincidía con el ticket impreso.
 *
 * Todo filtra por sucursal de forma explícita: en el panel el usuario puede
 * estar viendo otra sucursal, y el scope ambiental no sirve para eso.
 */
class ResumenCorte
{
    /**
     * Cálculo del turno a partir de lo vendido. Lo usa el POS antes de cerrar.
     *
     * @param  Carbon|string|null  $hasta  Permite recalcular un turno ya cerrado.
     */
    public static function precorte(Apertura $apertura, int $sucursalId, $hasta = null): array
    {
        $desde = Carbon::parse($apertura->created_at);
        $hasta = $hasta ? Carbon::parse($hasta) : Carbon::now();

        $ventas = Venta::query()
            ->withoutGlobalScope(\App\Scopes\SucursalFilterScope::class)
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
        $tarjeta = (float) $pagos->where('tipo', 'tarjeta')->sum('monto');
        $transferencia = (float) $pagos->where('tipo', 'transferencia')->sum('monto');

        // Las ventas del sitio web no pasan por este cajón, pero se reportan en
        // el corte porque el negocio las revisa junto con las del turno. Cuentan
        // por la hora de su primera visita, no por cuándo se compraron.
        $desdeEnLinea = self::inicioEnLinea($apertura, $sucursalId);
        $online = (float) Venta::query()
            ->enLineaConVisitaEntre($sucursalId, $desdeEnLinea, $hasta)
            ->where('estatus', 'activo')
            ->sum('total');

        $salidas = self::salidas($apertura->id, $sucursalId, $desde, $hasta);

        // Solo los capturados en este turno: los del panel exigen la imagen al
        // registrarse, y el POS la sube después de imprimir el ticket.
        $pagosSinFirma = EmpleadoPago::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('apertura_id', $apertura->id)
            ->where('estatus', 'activo')
            ->where(fn ($query) => $query->whereNull('imagen')->orWhere('imagen', ''))
            ->count();

        $fondoInicial = (float) $apertura->monto_apertura;
        $efectivoEsperado = $fondoInicial + $efectivoNeto - $salidas['egresos'] - $salidas['pagos_empleados'];
        $ingresos = $efectivoNeto + $tarjeta + $transferencia + $online;

        return [
            'apertura' => [
                'id' => $apertura->id,
                'fondo_inicial' => $fondoInicial,
                'abierta_en' => (string) $apertura->created_at,
            ],
            'periodo' => [
                'desde' => $desde->toDateTimeString(),
                'hasta' => $hasta->toDateTimeString(),
                'en_linea_desde' => $desdeEnLinea->toDateTimeString(),
            ],
            'ventas' => [
                'cantidad' => $activas->count(),
                'canceladas' => $ventas->where('estatus', 'cancelado')->count(),
                'total' => round((float) $activas->sum('total'), 2),
                'descuentos' => round((float) $activas->sum('descuento'), 2),
                'efectivo' => round($efectivoNeto, 2),
                'efectivo_recibido' => round($efectivoRecibido, 2),
                'cambio_entregado' => round($cambioEntregado, 2),
                'tarjeta' => round($tarjeta, 2),
                'transferencia' => round($transferencia, 2),
                'online' => round($online, 2),
            ],
            'salidas' => $salidas + [
                'total' => round($salidas['egresos'] + $salidas['pagos_empleados'], 2),
            ],
            'caja' => [
                'efectivo_esperado' => round($efectivoEsperado, 2),
            ],
            'resultado' => [
                'ingresos' => round($ingresos, 2),
                'ganancia' => self::ganancia($ingresos, $salidas),
            ],
            'pendientes' => [
                'pagos_sin_firma' => $pagosSinFirma,
            ],
        ];
    }

    /**
     * Desde cuándo cuentan las ventas en línea de un turno: el cierre del corte
     * anterior de la sucursal, o la apertura si es el primero.
     *
     * No se usa el día calendario porque hay días con dos o tres turnos y la
     * venta se contaría en todos. Con cortes consecutivos cada venta en línea cae
     * en exactamente uno: el primero que se cierra después de su visita. Si la
     * caja cierra antes de una visita y ese día no se abre otra, la venta entra
     * en el siguiente corte de la sucursal.
     */
    public static function inicioEnLinea(Apertura $apertura, int $sucursalId): Carbon
    {
        $abierta = Carbon::parse($apertura->created_at);

        $cierreAnterior = Corte::query()
            ->withoutGlobalScope(SucursalFilterScope::class)
            ->where('sucursal_id', $sucursalId)
            ->where('apertura_id', '!=', $apertura->id)
            ->where('fecha_final', '<=', $abierta)
            ->max('fecha_final');

        return $cierreAnterior ? Carbon::parse($cierreAnterior) : $abierta;
    }

    /**
     * Dinero que salió de la caja en el turno.
     *
     * Los movimientos capturados en el POS traen apertura_id y cuentan en su turno
     * sin importar la fecha declarada. Los capturados desde el panel no pertenecen
     * a ninguna caja, así que se cuentan por ventana. Todo egreso del POS es en
     * efectivo, así que no se separan por forma de pago.
     *
     * @return array{egresos: float, pagos_empleados: float}
     */
    public static function salidas(?int $aperturaId, int $sucursalId, Carbon $desde, Carbon $hasta): array
    {
        $delTurno = function ($query, string $columnaFecha) use ($aperturaId, $desde, $hasta) {
            $query->where(function ($query) use ($aperturaId, $desde, $hasta, $columnaFecha) {
                if ($aperturaId) {
                    $query->where('apertura_id', $aperturaId);
                }

                $query->orWhere(fn ($sinCaja) => $sinCaja
                    ->whereNull('apertura_id')
                    ->whereBetween($columnaFecha, [$desde, $hasta]));
            });
        };

        $egresos = Egreso::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estatus', 'activo')
            ->tap(fn ($query) => $delTurno($query, 'fecha_pago'))
            ->sum('monto');

        // EmpleadoPago no guarda sucursal: la hereda del empleado. Su scope global
        // es un closure que sigue la sucursal activa del usuario, así que se quita
        // (con él se va el de SoftDeletes, que se repone a mano).
        $pagosEmpleados = EmpleadoPago::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('estatus', 'activo')
            ->whereHas('empleado', fn ($empleado) => $empleado->where('sucursal_id', $sucursalId))
            ->tap(fn ($query) => $delTurno($query, 'created_at'))
            ->sum('monto');

        return [
            'egresos' => round((float) $egresos, 2),
            'pagos_empleados' => round((float) $pagosEmpleados, 2),
        ];
    }

    /**
     * Ganancia: todo lo que entró, se cobre como se cobre, menos lo que salió. A
     * diferencia del arqueo no cuenta el fondo, que no es venta.
     */
    public static function ganancia(float $ingresos, array $salidas): float
    {
        return round($ingresos - $salidas['egresos'] - $salidas['pagos_empleados'], 2);
    }

    /**
     * Un corte ya cerrado, con la misma forma que el ticket impreso. Las ventas
     * salen de lo que quedó guardado al cerrar; las salidas se recalculan con la
     * misma regla del precorte.
     */
    public static function deCorte(Corte $corte, ?array $precorte = null): array
    {
        $apertura = $corte->apertura_id
            ? Apertura::query()->withoutGlobalScopes()->find($corte->apertura_id)
            : null;

        if (! $precorte && $apertura) {
            $precorte = self::precorte($apertura, (int) $corte->sucursal_id, $corte->fecha_final);
        }

        $salidas = $corte->salidas();
        $ingresos = (float) $corte->efectivo + (float) $corte->tarjeta
            + (float) $corte->transferencia + (float) $corte->total_online;

        $contado = (float) $corte->total_caja;
        $fondoInicial = $apertura ? (float) $apertura->monto_apertura : null;
        $esperado = $fondoInicial === null
            ? null
            : round($fondoInicial + (float) $corte->efectivo - $salidas['egresos'] - $salidas['pagos_empleados'], 2);

        return [
            'id' => $corte->id,
            'sucursal' => optional($corte->sucursal)->razon_social,
            'cajero' => optional($corte->user)->nombre_completo,
            'fecha_inicio' => (string) $corte->fecha_inicio,
            'fecha_final' => (string) $corte->fecha_final,
            'total' => (float) $corte->total,
            'efectivo' => (float) $corte->efectivo,
            'tarjeta' => (float) $corte->tarjeta,
            'transferencia' => (float) $corte->transferencia,
            'online' => (float) $corte->total_online,
            'fondo_inicial' => $fondoInicial,
            'egresos' => $salidas['egresos'],
            'pagos_empleados' => $salidas['pagos_empleados'],
            'ingresos' => round($ingresos, 2),
            'ganancia' => self::ganancia($ingresos, $salidas),
            'efectivo_contado' => $contado,
            'fondo_final' => (float) $corte->fondo_final,
            'efectivo_esperado' => $esperado,
            'diferencia' => $esperado === null ? null : round($contado - $esperado, 2),
            'detalle' => $precorte,
        ];
    }
}
