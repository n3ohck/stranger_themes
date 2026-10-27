@extends(backpack_view('blank'))

@php
    use App\Support\Fechas;

    $dinero = fn ($valor) => '$' . number_format((float) $valor, 2, '.', ',');
    $ventas = $corte['detalle']['ventas'] ?? null;
@endphp

{{--
    Mismo contenido y orden que el ticket que imprime el POS al cerrar caja
    (resources/js/pos/components/TicketCorte.vue). Los números salen de
    App\Support\Caja\ResumenCorte, igual que allá. Si cambia uno, cambiar el otro.
--}}

@section('header')
    <section class="container-fluid d-print-none">
        <h2>
            <span class="text-capitalize">{{ $titulo }}</span>
            <small>{{ Fechas::turno($corte['fecha_inicio'], $corte['fecha_final']) }}</small>
            <small><a href="{{ url(config('backpack.base.route_prefix') . '/corte') }}" class="hidden-print font-sm">
                <i class="la la-angle-double-left"></i> Volver a <span>cortes</span>
            </a></small>
        </h2>
    </section>
@endsection

@section('content')
    <style>
        .ticket-corte {
            width: 72mm;
            margin: 0 auto;
            padding: 16px;
            background: #fff;
            color: #000;
            font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
            font-size: 13px;
            line-height: 1.4;
        }
        .ticket-corte .fila { display: flex; justify-content: space-between; gap: 8px; }
        .ticket-corte .fila span:last-child { text-align: right; }
        .ticket-corte .sep { border-top: 1px dashed #000; margin: 8px 0; }
        .ticket-corte .titulo { font-weight: bold; text-transform: uppercase; }
        .ticket-corte .fuerte { font-weight: bold; }

        @media print {
            body * { visibility: hidden; }
            #ticket, #ticket * { visibility: visible; }
            #ticket { position: absolute; left: 0; top: 0; width: 48mm; padding: 0; font-size: 9pt; }
            @page { size: 58mm auto; margin: 0; }
        }
    </style>

    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-body bg-light">
                    <div id="ticket" class="ticket-corte">
                        <div style="text-align: center;">
                            <div class="titulo" style="font-size: 15px;">Corte de Caja</div>
                            <div>{{ $corte['sucursal'] }}</div>
                        </div>

                        <div class="sep"></div>

                        <div class="fila"><span>Corte</span><span class="fuerte">#{{ $corte['id'] }}</span></div>
                        <div class="fila"><span>Cajero</span><span>{{ $corte['cajero'] }}</span></div>
                        <div class="fila"><span>Desde</span><span>{{ Fechas::conDiaYHora($corte['fecha_inicio']) }}</span></div>
                        <div class="fila"><span>Hasta</span><span>{{ $corte['fecha_final'] ? Fechas::conDiaYHora($corte['fecha_final']) : '-' }}</span></div>

                        <div class="sep"></div>
                        <div class="titulo">Ventas</div>

                        <div class="fila"><span>Tickets</span><span>{{ $ventas['cantidad'] ?? '-' }}</span></div>
                        @if (! empty($ventas['canceladas']))
                            <div class="fila"><span>Canceladas</span><span>{{ $ventas['canceladas'] }}</span></div>
                        @endif
                        @if (! empty($ventas['descuentos']))
                            <div class="fila"><span>Descuentos</span><span>-{{ $dinero($ventas['descuentos']) }}</span></div>
                        @endif
                        <div class="fila fuerte" style="margin-top: 4px;"><span>Total vendido</span><span>{{ $dinero($corte['total']) }}</span></div>

                        <div class="sep"></div>
                        <div class="titulo">Formas de pago</div>

                        <div class="fila"><span>Efectivo</span><span>{{ $dinero($corte['efectivo']) }}</span></div>
                        <div class="fila"><span>Tarjeta</span><span>{{ $dinero($corte['tarjeta']) }}</span></div>
                        <div class="fila"><span>Transferencia</span><span>{{ $dinero($corte['transferencia']) }}</span></div>
                        @if ($corte['online'] > 0)
                            <div class="fila"><span>En línea</span><span>{{ $dinero($corte['online']) }}</span></div>
                        @endif

                        <div class="sep"></div>
                        <div class="titulo">Ganancia</div>

                        <div class="fila"><span>Ingresos</span><span>{{ $dinero($corte['ingresos']) }}</span></div>
                        <div class="fila"><span>- Egresos</span><span>{{ $dinero($corte['egresos']) }}</span></div>
                        <div class="fila"><span>- Pago empleados</span><span>{{ $dinero($corte['pagos_empleados']) }}</span></div>
                        <div class="fila fuerte" style="margin-top: 4px;"><span>Ganancia</span><span>{{ $dinero($corte['ganancia']) }}</span></div>

                        <div class="sep"></div>
                        <div class="titulo">Caja</div>

                        <div class="fila"><span>Fondo inicial</span><span>{{ $dinero($corte['fondo_inicial'] ?? 0) }}</span></div>
                        <div class="fila"><span>+ Efectivo</span><span>{{ $dinero($corte['efectivo']) }}</span></div>
                        <div class="fila"><span>- Egresos</span><span>{{ $dinero($corte['egresos']) }}</span></div>
                        <div class="fila"><span>- Pago empleados</span><span>{{ $dinero($corte['pagos_empleados']) }}</span></div>
                        <div class="fila fuerte" style="margin-top: 4px;"><span>Esperado</span><span>{{ $dinero($corte['efectivo_esperado'] ?? 0) }}</span></div>
                        <div class="fila fuerte"><span>Contado</span><span>{{ $dinero($corte['efectivo_contado']) }}</span></div>
                        @if ($corte['diferencia'] !== null)
                            <div class="fila fuerte">
                                <span>Diferencia</span>
                                <span>{{ $corte['diferencia'] > 0 ? '+' : '' }}{{ $dinero($corte['diferencia']) }}</span>
                            </div>
                        @endif
                        @if ($corte['fondo_final'] > 0)
                            <div class="fila"><span>Fondo que queda</span><span>{{ $dinero($corte['fondo_final']) }}</span></div>
                        @endif

                        <div class="sep"></div>

                        <div style="text-align: center; margin-top: 32px;">_______________________</div>
                        <div style="text-align: center;">Firma del cajero</div>
                    </div>
                </div>
                <div class="card-footer d-print-none text-center">
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="la la-print"></i> Imprimir
                    </button>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/payment-report/' . $corte['id'] . '/empleado') }}" target="_blank" class="btn btn-link">Comprobantes de pagos</a>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/payment-report/' . $corte['id'] . '/egreso') }}" target="_blank" class="btn btn-link">Comprobantes de egresos</a>
                </div>
            </div>
        </div>
    </div>
@endsection
