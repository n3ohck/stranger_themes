@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
      trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
      $crud->entity_name_plural => url($crud->route),
      trans('backpack::crud.preview') => false,
    ];

    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;

    /**
     * Redondeo especial SOLO para DESCUENTOS (montos):
     * - dec <= .49  -> floor
     * - dec == .50  -> se queda igual
     * - dec >= .51  -> ceil
     */
    $roundDiscount = function ($value) {
        $value = (float) $value;

        // seguridad por si llega negativo: redondeamos por magnitud y restauramos signo
        $sign = $value < 0 ? -1 : 1;
        $abs  = abs($value);

        $int = floor($abs);
        $dec = $abs - $int;

        if ($dec <= 0.49) {
            $res = floor($abs);
        } elseif ($dec >= 0.51) {
            $res = ceil($abs);
        } else {
            // EXACTAMENTE 0.50 -> se queda igual
            $res = $abs;
        }

        return $res * $sign;
    };
@endphp

@section('content')
    <div class="w-full">
        <div class="w-full">

            @if ($crud->model->translationEnabled())
                <div class="row">
                    <div class="col-md-12 mb-2">
                        <div class="btn-group float-right">
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {{ trans('backpack::crud.language') }}:
                                {{ $crud->model->getAvailableLocales()[request()->input('locale') ? request()->input('locale') : App::getLocale()] }}
                                &nbsp; <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                @foreach ($crud->model->getAvailableLocales() as $key => $locale)
                                    <a class="dropdown-item" href="{{ url($crud->route.'/'.$entry->getKey().'/show') }}?locale={{ $key }}">{{ $locale }}</a>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- CONTENEDOR FULL --}}
            <div class="w-full space-y-4">

                {{-- CARD: Generales --}}
                <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Generales</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Folio: <span class="font-semibold text-slate-700">{{ $entry->folio ?? 'N/A' }}</span>
                                · Estatus: <span class="font-semibold text-slate-700">{{ $entry->estatus ?? 'N/A' }}</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ url($crud->route) }}"
                               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                <i class="la la-angle-double-left"></i> Volver
                            </a>

                            <a href="javascript: window.print();"
                               class="inline-flex items-center gap-2 rounded-xl bg-purple-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-purple-800">
                                <i class="la la-print"></i> Imprimir
                            </a>
                        </div>
                    </div>

                    <div class="px-4 py-3">
                        @php
                            $only = [
                                'nombre',
                                'email',
                                'telefono',
                                'folio',
                                'total',
                                'created_at',
                                'estatus',
                                'fecha_cancelacion',
                            ];

                            $columns = collect($crud->columns())
                                ->filter(function($column) use ($only) {
                                    $name = $column['name'] ?? $column['key'] ?? null;
                                    return $name && in_array($name, $only, true);
                                })
                                ->sortBy(function($column) use ($only) {
                                    $name = $column['name'] ?? $column['key'] ?? '';
                                    return array_search($name, $only, true);
                                });
                        @endphp

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <tbody class="divide-y divide-slate-100">
                                @foreach ($columns as $column)
                                    <tr class="align-top">
                                        <td class="w-56 py-3 pr-4 font-semibold text-slate-700">
                                            {!! $column['label'] !!}:
                                        </td>
                                        <td class="py-3 text-slate-800">
                                            @if (!isset($column['type']))
                                                @include('crud::columns.text')
                                            @else
                                                @if(view()->exists('vendor.backpack.crud.columns.'.$column['type']))
                                                    @include('vendor.backpack.crud.columns.'.$column['type'])
                                                @else
                                                    @if(view()->exists('crud::columns.'.$column['type']))
                                                        @include('crud::columns.'.$column['type'])
                                                    @else
                                                        @include('crud::columns.text')
                                                    @endif
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                @if ($crud->buttons()->where('stack', 'line')->count())
                                    <tr>
                                        <td class="w-56 py-3 pr-4 font-semibold text-slate-700">
                                            {{ trans('backpack::crud.actions') }}:
                                        </td>
                                        <td class="py-3">
                                            @include('crud::inc.button_stack', ['stack' => 'line'])
                                        </td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- CARD: Reservaciones --}}
                @if ($entry->reservaciones->count())
                    <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h3 class="text-base font-semibold text-slate-900">Reservaciones</h3>
                        </div>

                        <div class="px-4 py-3">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                    <tr class="divide-x divide-slate-200">
                                        <th class="px-3 py-2 text-left">Cliente</th>
                                        <th class="px-3 py-2 text-left">Recorrido</th>
                                        <th class="px-3 py-2 text-right">Cantidad</th>
                                        <th class="px-3 py-2 text-left">Fecha</th>
                                        <th class="px-3 py-2 text-left">Estado</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                    @foreach($entry->reservaciones as $reservacion)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2">{{ $reservacion->nombre_cliente }}</td>
                                            <td class="px-3 py-2">{{ $reservacion->producto->descripcion }}</td>
                                            <td class="px-3 py-2 text-right">{{ $reservacion->cantidad_personas }}</td>
                                            <td class="px-3 py-2">{{ $reservacion->fecha }}</td>
                                            <td class="px-3 py-2">{{ $reservacion->estado }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- CARD: Pagos (sin redondeo especial) --}}
                @if ($entry->pagos->count())
                    <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h3 class="text-base font-semibold text-slate-900">Pagos</h3>
                        </div>

                        <div class="px-4 py-3">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                    <tr class="divide-x divide-slate-200">
                                        <th class="px-3 py-2 text-left">Tipo</th>
                                        <th class="px-3 py-2 text-left">Referencia</th>
                                        <th class="px-3 py-2 text-right">Monto</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                    @foreach($entry->pagos as $pago)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2">{{ $pago->tipo }}</td>
                                            <td class="px-3 py-2">{{ $pago->referencia ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format((float)$pago->monto, 2, '.', ',') }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- CARD: Productos (descuento con redondeo especial) --}}
                @if ($entry->productos->count())
                    <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h3 class="text-base font-semibold text-slate-900">Productos</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Regla descuento: ≤ .49 abajo · .50 igual · ≥ .51 arriba (solo aplica a descuentos).
                            </p>
                        </div>

                        <div class="px-4 py-3">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                    <tr class="divide-x divide-slate-200">
                                        <th class="px-3 py-2 text-left">Producto</th>
                                        <th class="px-3 py-2 text-right">Cantidad</th>
                                        <th class="px-3 py-2 text-right">Precio</th>
                                        <th class="px-3 py-2 text-right">% Desc.</th>
                                        <th class="px-3 py-2 text-right">Desc. $</th>
                                        <th class="px-3 py-2 text-right">Precio c/Desc.</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                        <th class="px-3 py-2 text-left">Código</th>
                                    </tr>
                                    </thead>

                                    <tbody class="divide-y divide-slate-100">
                                    @foreach($entry->productos as $producto)
                                        @php
                                            $paymentsOnline = $entry->pagos->where('tipo', 'online')->count();

                                            // NO redondear precio/cantidad (solo descuentos)
                                            $precio = (float) $producto->precio;
                                            $cantidad = (float) $producto->cantidad;
                                            $porcentaje = (float) $producto->porcentaje_descuento;

                                            // Ajuste online (se conserva por compatibilidad con tu data actual)
                                            $totalLineaOriginal = (float) $producto->total;
                                            $descuentoGuardado  = (float) $producto->descuento;

                                            if ($paymentsOnline) {
                                                $totalLineaOriginal = $descuentoGuardado;
                                                $descuentoGuardado  = ((float) $producto->total) - $descuentoGuardado;
                                            }

                                            // Cálculo base por porcentaje
                                            if ($precio > 0 && $porcentaje > 0) {
                                                $precioConDescuentoRaw = $precio * (1 - ($porcentaje / 100));
                                                $descuentoUnitarioRaw  = $precio - $precioConDescuentoRaw;
                                            } else {
                                                $precioConDescuentoRaw = $precio;
                                                $descuentoUnitarioRaw  = 0;
                                            }

                                            // Regla especial SOLO para descuentos / derivados del descuento
                                            $descuentoUnitario  = max(0, $roundDiscount($descuentoUnitarioRaw));
                                            $precioConDescuento = max(0, $roundDiscount($precioConDescuentoRaw));
                                            $totalConDescuento  = max(0, $roundDiscount($precioConDescuento * $cantidad));

                                            $codigo = optional($producto->descuentoEntity)->codigo ?? $producto->codigo_descuento ?? 'N/A';
                                        @endphp

                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2 font-medium text-slate-900">
                                                {{ $producto->producto->descripcion }}
                                            </td>

                                            {{-- precio y cantidad tal cual (con 2 decimales por si llegan) --}}
                                            <td class="px-3 py-2 text-right">{{ number_format($cantidad, 2, '.', ',') }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($precio, 2, '.', ',') }}</td>

                                            <td class="px-3 py-2 text-right">{{ number_format($porcentaje, 2, '.', ',') }}</td>

                                            {{-- descuentos con regla especial --}}
                                            <td class="px-3 py-2 text-right">{{ number_format($descuentoUnitario, 2, '.', ',') }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($precioConDescuento, 2, '.', ',') }}</td>
                                            <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ number_format($totalConDescuento, 2, '.', ',') }}</td>

                                            <td class="px-3 py-2">
                                                <span class="inline-block max-w-[260px] truncate align-middle text-slate-700" title="{{ $codigo }}">
                                                    {{ $codigo }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Total productos (con regla especial, porque viene del descuento) --}}
                            @php
                                $sumTotal = $entry->productos->sum(function($p) use ($roundDiscount) {
                                    $precio = (float) $p->precio;
                                    $cantidad = (float) $p->cantidad;
                                    $porcentaje = (float) $p->porcentaje_descuento;

                                    if ($precio > 0 && $porcentaje > 0) {
                                        $precioConDescuentoRaw = $precio * (1 - ($porcentaje / 100));
                                    } else {
                                        $precioConDescuentoRaw = $precio;
                                    }

                                    $precioConDescuento = max(0, $roundDiscount($precioConDescuentoRaw));
                                    $total = $precioConDescuento * $cantidad;

                                    return max(0, $roundDiscount($total));
                                });
                            @endphp

                            <div class="mt-3 flex items-center justify-end">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                                    <span class="text-slate-600">Total productos:</span>
                                    <span class="ml-2 font-semibold text-slate-900">{{ number_format((float)$sumTotal, 2, '.', ',') }}</span>
                                </div>
                            </div>

                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endsection

@section('after_styles')
    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Backpack --}}
    <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/crud.css').'?v='.config('backpack.base.cachebusting_string') }}">
    <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/show.css').'?v='.config('backpack.base.cachebusting_string') }}">
@endsection

@section('after_scripts')
    <script src="{{ asset('packages/backpack/crud/js/crud.js').'?v='.config('backpack.base.cachebusting_string') }}"></script>
    <script src="{{ asset('packages/backpack/crud/js/show.js').'?v='.config('backpack.base.cachebusting_string') }}"></script>
@endsection
