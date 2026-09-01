<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\VentaRequest;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaProducto;
use App\Scopes\SucursalFilterScope;
use App\Support\SucursalActiva;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Actions\VentaAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class VentaCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class VentaCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \App\Traits\DateTrait;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Venta::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/venta');
        CRUD::setEntityNameStrings('venta', 'ventas');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->crud->addColumns([
            [
                'name' => 'folio',
                'label' => 'Folio',
                'type' => 'text'
            ],
            [
                'name' => 'nombre',
                'label' => 'Nombre',
                'type' => 'text'
            ],
            [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'text'
            ],
            [
                'name' => 'telefono',
                'label' => 'Telefono',
                'type' => 'text'
            ],
            [
                'name' => 'created_at',
                'label' => 'Fecha',
                'type' => 'datetime'
            ],
            [
                'name' => 'total',
                'label' => 'Total',
                'type' => 'number',
                'decimals' => 2,
                'prefix' => '$',
            ],
            [
                // 1-n relationship
                'label' => 'Sucursal', // Table column heading
                'type' => 'select',
                'name' => 'sucursal_id', // the column that contains the ID of that connected entity;
                'entity' => 'sucursal', // the method that defines the relationship in your Model
                'attribute' => 'razon_social', // foreign key attribute that is shown to user
                'model' => Sucursal::class, // foreign key model
            ],
            [
                // 1-n relationship
                'label' => 'Usuario', // Table column heading
                'type' => 'select',
                'name' => 'user_id', // the column that contains the ID of that connected entity;
                'entity' => 'user', // the method that defines the relationship in your Model
                'attribute' => 'name', // foreign key attribute that is shown to user
                'model' => User::class, // foreign key model
            ],
            [
                'name' => 'estatus',
                'label' => 'Estatus',
                'type' => 'text'
            ],
        ]);
        $this->crud->removeColumn('user_id_cancelacion');
        $this->crud->orderBy('created_at', 'desc');
        $this->crud->enableExportButtons();
        $this->crud->addFilter([
            'name' => 'sucursal_id',
            'type' => 'select2',
            'label' => 'Sucursal'
        ], function () {
            return Sucursal::pluck('razon_social', 'id')->toArray();
        }, function ($query, $value) {
            $query->where('sucursal_id', $value);
        });

        $this->crud->addFilter([
            'name' => 'user_id',
            'type' => 'select2',
            'label' => 'Usuario'
        ], function () {
            return User::all()->pluck('user', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'user_id', $value);
        });

        $this->crud->addFilter([
            'name' => 'estatus',
            'type' => 'dropdown',
            'label' => 'Estatus'
        ], [
            'activo' => 'Activo',
            'cancelado' => 'Cancelado'
        ], function ($value) {
            $this->crud->addClause('where', 'estatus', $value);
        });

        $this->crud->addFilter([
            'type' => 'date_range',
            'name' => 'created_at',
            'label' => 'Fecha'
        ],
            false,
            function ($value) { // <-- este $value contiene el JSON con 'from' y 'to'
                if ($value) {
                    $dates = json_decode($value, true); // decodificamos el JSON del rango
                    $this->crud->addClause('whereBetween', 'created_at', [$dates['from'], $dates['to']]);
                }
            });
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(VentaRequest::class);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }


    public function fetch(Request $request)
    {
        try {
            $search = (object)[
                'folio' => $request->get('folio'),
                'start_date' => $this->makeDate($request->get('start_date')),
                'end_date' => $this->makeDate($request->get('end_date')),
                'status' => $request->get('status'),
                'venta_id' => $request->get('venta_id'),
                'user_id' => $request->get('user_id')
            ];

            if ($search->start_date) {
                $search->start_date = $search->start_date->format('Y-m-d H:i');
            }

            if ($search->end_date) {
                $search->end_date = $search->end_date->format('Y-m-d H:i');
            }

            $commonWith = [
                'productos.producto',
                'pagos',
                'reservaciones.producto',
            ];

            $searchall = $search;
            $searchall->user_id = null;
            $ventas = Venta::query()
                ->whereHas('pagos', fn($q) => $q->where('tipo', '!=', 'online'))
                ->search($search)
                ->with($commonWith)
                ->orderByDesc('created_at')
                ->get();

            $start = Carbon::parse($search->start_date)->startOfDay();
            $end = Carbon::parse($start)->endOfDay();
            // withoutGlobalScopes es necesario porque estas ventas se crean con
            // user_id = 1, pero hay que reponer el filtro de sucursal a mano: sin
            // él la consulta devuelve las ventas en línea de todas las sucursales.
            $ventasOnlineExtra = Venta::query()
                ->withoutGlobalScopes()
                ->whereHas('pagos', fn($q) => $q->where('tipo', 'online'))
                ->whereHas('reservaciones', fn($q) => $q->whereBetween('fecha', [$start, $end]))
                ->when(SucursalActiva::id(), fn($q, $sucursalId) => $q->where('sucursal_id', $sucursalId))
                ->with($commonWith)
                ->get();

            $ventasSistema = $ventas
                ->merge($ventasOnlineExtra)
                ->unique('id')
                ->sortByDesc('created_at')
                ->values();

            return response()->json([
                'message' => 'Consulta realizada con exito',
                'ventas' => $ventasSistema,
                'qty' => $ventasSistema->count()
            ], 200);
        } catch (\Exception $e) {
            return response()
                ->json([
                    'error' => $e->getMessage()
                ], 400);
        }
    }

    public function make(Request $request)
    {
        try {
            if (!$request->has('ventas')) throw new \Exception('No se han enviado ventas');
            $ventas = $request->input('ventas');
            DB::beginTransaction();
            foreach ($ventas as $venta) {
                if (!isset($venta['productos'])) throw new \Exception('No se han enviado productos');
                if (!isset($venta['pagos'])) throw new \Exception('No se han enviado pagos');
            }
            $ventas = (new VentaAction())->do($request->ventas);
            DB::commit();
            return response()->json(['ventas' => $ventas, 'qty' => count($ventas)], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function publicMake(Request $request)
    {
        try {
            if (!$request->has('ventas')) throw new \Exception('No se han enviado ventas');
            $ventas = $request->input('ventas');
            DB::beginTransaction();
            foreach ($ventas as $venta) {
                if (!isset($venta['productos'])) throw new \Exception('No se han enviado productos');
                if (!isset($venta['pagos'])) throw new \Exception('No se han enviado pagos');
            }
            $ventas = (new VentaAction())->saleOnline($request->ventas);
            DB::commit();

            return response()->json(['ventas' => $ventas, 'qty' => count($ventas)], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            // El detalle va al log del servidor, no a la respuesta: este endpoint
            // es público y antes devolvía el stack trace completo con las rutas
            // absolutas del servidor, unos 16 KB por error.
            Log::error('Falló el registro de una venta online', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile() . ':' . $e->getLine(),
                'payload' => $request->input('ventas'),
            ]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function cancel(Request $request)
    {
        try {
            if (!$request->has('ventas')) throw new \Exception('No se ha enviado las ventas a cancelar');
            $ventasCancelar = $request->input('ventas');
            DB::beginTransaction();
            $ventas = (new VentaAction())->cancelVentas($ventasCancelar);
            DB::commit();
            return response()->json(['ventas' => $ventas, 'qty' => count($ventas)], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function resumen(Request $request)
    {
        try {
            $tz = config('app.display_timezone', 'America/Chihuahua');
            $params = $request->all();

            if (!isset($params['dates'])) {
                $startLocal = Carbon::now($tz)->startOfDay();
                $endLocal   = Carbon::now($tz)->endOfDay();
            } else {
                $startLocal = Carbon::parse($params['dates'][0], $tz)->startOfDay();
                $endLocal   = Carbon::parse($params['dates'][1], $tz)->endOfDay();
            }

            // Pasa a UTC para consultar en DB (que guarda UTC)
            $params['dates'] = [
                $startLocal->clone()->timezone('UTC'),
                $endLocal->clone()->timezone('UTC'),
            ];

            $totalVentas = 0;
            $totaEgresos = (new EgresoCrudController)->getTotal($params['dates']);
            $salarios    = (new EmpleadoPagoCrudController)->getTotal($params['dates']);

            /**
             * Regla de redondeo SOLO para DESCUENTOS (montos):
             * - dec <= .49  -> floor
             * - dec == .50  -> se queda igual
             * - dec >= .51  -> ceil
             */
            $roundDiscount = function (float $value): float {
                $sign = $value < 0 ? -1 : 1;
                $abs  = abs($value);

                $int = floor($abs);
                $dec = $abs - $int;

                if ($dec <= 0.49) {
                    $res = floor($abs);
                } elseif ($dec >= 0.51) {
                    $res = ceil($abs);
                } else {
                    $res = $abs; // EXACTAMENTE .50
                }

                return $res * $sign;
            };

            /**
             * Calcula el descuento total REAL de una venta:
             * - Usa porcentaje_descuento y precio
             * - Calcula a cuántas personas/unidades se aplicó para cuadrar contra total real de línea
             * - Aplica redondeo SOLO al descuento unitario (regla cliente)
             */
            $calcVentaDiscountAndCodes = function (Venta $venta) use ($roundDiscount): array {

                $codes = collect();

                $discountTotal = 0.0;

                foreach ($venta->productos as $vp) {

                    $precio     = (float) $vp->precio;
                    $cantidad   = (float) $vp->cantidad;
                    $porcentaje = (float) $vp->porcentaje_descuento;
                    $totalLineaObjetivo = (float) $vp->total;

                    // código descuento (del entity o fallback)
                    $code = optional($vp->descuentoEntity)->codigo
                        ?? ($vp->codigo_descuento ?? null);

                    if ($code) {
                        $codes->push($code);
                    }

                    if ($precio <= 0 || $cantidad <= 0 || $porcentaje <= 0) {
                        continue;
                    }

                    // descuento unitario raw por porcentaje
                    $descuentoUnitarioRaw = $precio * ($porcentaje / 100);

                    // regla cliente SOLO en descuentos
                    $descuentoUnitario = max(0.0, $roundDiscount($descuentoUnitarioRaw));

                    if ($descuentoUnitario <= 0) {
                        continue;
                    }

                    // total sin descuento de la línea
                    $subtotalLinea = $precio * $cantidad;

                    // descuento total necesario (según el total real guardado en la línea)
                    $descuentoNecesario = $subtotalLinea - $totalLineaObjetivo;

                    // si no hay descuento necesario, no aplica
                    if ($descuentoNecesario <= 0) {
                        continue;
                    }

                    // cuántas unidades/personas tuvieron descuento
                    $aplicaA = (int) round($descuentoNecesario / $descuentoUnitario);

                    // clamp [0, cantidad]
                    $aplicaA = max(0, min((int) round($cantidad), $aplicaA));

                    // descuento de esta línea = descuento_unitario * aplicaA
                    $discountTotal += ($descuentoUnitario * $aplicaA);
                }

                // si hay varios códigos, los mostramos juntos (sin repetir)
                $codesFinal = $codes
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'descuento_total' => round($discountTotal, 2),
                    'codigo_descuento' => $codesFinal ?: 'N/A',
                ];
            };

            // Sucursal del reporte: la que se eligió en el filtro de la pantalla y,
            // si no viene, la sucursal activa del usuario. Antes esta consulta usaba
            // la sucursal del usuario mientras el resto del reporte usaba la del
            // filtro, así que al consultar otra sucursal las ventas en línea seguían
            // siendo las propias.
            $sucursalReporte = $params['sucursal'] ?? SucursalActiva::id();

            // 1) Ventas en línea: se buscan aparte porque su created_at es la fecha
            //    de compra y el reporte las ubica por la fecha de la experiencia.
            $ventasOnlineExtra = Venta::query()
                ->withoutGlobalScopes()
                ->whereHas('pagos', fn($q) => $q->where('tipo', 'online'))
                ->whereHas('reservaciones', fn($q) => $q->whereBetween('fecha', [$startLocal, $endLocal]))
                ->when($sucursalReporte, fn($q) => $q->where('sucursal_id', $sucursalReporte))
                ->with([
                    'user',
                    'sucursal',
                    'pagos',
                    'reservaciones',
                    'productos.descuentoEntity',
                    'productos'
                ])
                ->get();

            // 2) Ventas normales
            $ventas = Venta::query()
                ->with([
                    'user',
                    'sucursal',
                    'pagos',
                    'reservaciones',
                    'productos.descuentoEntity',
                    'productos'
                ])
                ->whereHas('pagos', fn($q) => $q->where('tipo', '!=', 'online'))
                ->Filters($params)
                ->get();

            // Mapper (misma lógica para ambas colecciones)
            $mapVenta = function (Venta $venta) use (&$totalVentas, $calcVentaDiscountAndCodes) {

                $hasOnline = $venta->pagos->where('tipo', 'online')->count() > 0;

                // Total según tu lógica anterior
                if ($hasOnline) {
                    $totalVenta = ($venta->total - ($venta->descuento ?? 0));
                    $venta->created_at = optional($venta->reservaciones->first())->created_at ?? $venta->created_at;
                } else {
                    $totalVenta = $venta->total;
                }

                // ✅ descuento y código correctos (desde productos)
                $calc = $calcVentaDiscountAndCodes($venta);

                if ($venta->estatus === 'activo') {
                    $totalVentas += $totalVenta;
                }

                return [
                    'folio' => $venta->folio,
                    'created_at' => $venta->created_at,
                    'tarjeta' => $venta->pagos->where('tipo', 'tarjeta')->sum('monto'),
                    'efectivo' => $venta->pagos->where('tipo', 'efectivo')->sum('monto'),
                    'online' => $venta->pagos->where('tipo', 'online')->sum('monto'),
                    'descuento' => $calc['descuento_total'],              // ✅ correcto
                    'total' => $totalVenta,
                    'cambio' => $venta->pagos->sum('cambio'),
                    'estatus' => $venta->estatus,
                    'sucursal' => optional($venta->sucursal)->razon_social,
                    'codigo_descuento' => $calc['codigo_descuento'],      // ✅ correcto
                ];
            };

            $ventasFinal = $ventas->map($mapVenta)
                ->merge($ventasOnlineExtra->map($mapVenta))
                ->sortByDesc('created_at')
                ->values();

            $cantidadReservaciones = Reserva::query()
                ->where('estado', 'confirmada')
                ->whereBetween('fecha', [$startLocal, $endLocal])
                ->sum('cantidad_personas');

            $utilidad_operativa = $totalVentas - ($totaEgresos + $salarios);

            return response()->json([
                'message' => 'Consulta realizada con exito',
                'ventas' => $ventasFinal,
                'cantidad_ventas' => $ventasFinal->count(),
                'cantidad_reservaciones' => $cantidadReservaciones,
                'total_ventas' => number_format($totalVentas, 2, '.', ','),
                'total_egresos' => number_format($totaEgresos, 2, '.', ','),
                'total_salarios' => number_format($salarios, 2, '.', ','),
                'utilidad_operativa' => number_format($utilidad_operativa, 2, '.', ','),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resumenProductos(Request $request)
    {
        try {
            $params = $request->all();
            $tz = config('app.display_timezone', 'America/Chihuahua');
            if (!isset($params['dates'])) {
                $startLocal = Carbon::now($tz)->startOfDay();
                $endLocal = Carbon::now($tz)->endOfDay();
            } else {
                $startLocal = Carbon::parse($params['dates'][0], $tz)->startOfDay();
                $endLocal = Carbon::parse($params['dates'][1], $tz)->endOfDay();
            }
            // Pasa a UTC para consultar en DB (que guarda UTC)
            $params['dates'] = [
                $startLocal->clone()->timezone('UTC'),
                $endLocal->clone()->timezone('UTC'),
            ];

            $productos = VentaProducto::query()
                ->whereHas('venta', function ($query) use ($params) {
                    $query
                        ->where('estatus', 'activo')
                        ->Filters($params);
                })
                ->with([
                    'producto'
                ])
                ->get()
                ->groupBy('producto_id')
                ->map(function ($productosAgrupados) {
                    return [
                        'producto' => $productosAgrupados->first()->producto->descripcion,
                        'cantidad' => $productosAgrupados->sum('cantidad'),
                        'total' => $productosAgrupados->sum('total')
                    ];
                });
            return response()->json([
                'message' => 'Consulta realizada con exito',
                'productos' => $productos

            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resumenProductosDescuentos(Request $request)
    {
        try {
            $params = $request->all();
            $tz = config('app.display_timezone', 'America/Chihuahua');

            if (!isset($params['dates'])) {
                $startLocal = Carbon::now($tz)->startOfDay();
                $endLocal   = Carbon::now($tz)->endOfDay();
            } else {
                $startLocal = Carbon::parse($params['dates'][0], $tz)->startOfDay();
                $endLocal   = Carbon::parse($params['dates'][1], $tz)->endOfDay();
            }

            // Pasar a UTC para DB
            $params['dates'] = [
                $startLocal->clone()->timezone('UTC'),
                $endLocal->clone()->timezone('UTC'),
            ];

            /**
             * Regla de redondeo SOLO para descuentos (montos):
             * - dec <= .49  -> floor
             * - dec == .50  -> se queda igual
             * - dec >= .51  -> ceil
             */
            $roundDiscount = function (float $value): float {
                $sign = $value < 0 ? -1 : 1;
                $abs  = abs($value);

                $int = floor($abs);
                $dec = $abs - $int;

                if ($dec <= 0.49) {
                    $res = floor($abs);
                } elseif ($dec >= 0.51) {
                    $res = ceil($abs);
                } else {
                    // exactamente .50
                    $res = $abs;
                }

                return $res * $sign;
            };

            $productos = VentaProducto::query()
                ->whereHas('venta', function ($query) use ($params) {
                    $query
                        ->where('estatus', 'activo')
                        ->Filters($params);
                })
                ->with([
                    'venta.pagos',
                    'producto',
                    'descuentoEntity'
                ])
                ->where('descuento', '!=', 0)
                ->get()
                ->map(function ($producto) use ($roundDiscount) {

                    $paymentsOnline = $producto->venta->pagos
                        ->where('tipo', 'online')
                        ->count();

                    // Compatibilidad con lógica previa (online)
                    if ($paymentsOnline) {
                        $totalOriginal        = (float) $producto->total;
                        $producto->total      = (float) $producto->descuento;
                        $producto->descuento  = $totalOriginal - (float) $producto->descuento;
                    }

                    // Base (NO redondear)
                    $precio     = (float) $producto->precio;                 // unitario
                    $cantidad   = (float) $producto->cantidad;               // qty/personas
                    $porcentaje = (float) $producto->porcentaje_descuento;   // %

                    // Total real de la línea (objetivo a cuadrar)
                    $totalObjetivo = (float) $producto->total;

                    // Subtotal sin descuento
                    $subtotalLinea = $precio * $cantidad;

                    // 1) Descuento unitario raw por porcentaje
                    $descuentoUnitarioRaw = ($precio > 0 && $porcentaje > 0)
                        ? ($precio * ($porcentaje / 100))
                        : 0.0;

                    // 2) Aplicar regla SOLO al descuento unitario
                    $descuentoUnitario = max(0.0, $roundDiscount($descuentoUnitarioRaw));

                    // 3) Precio unitario con descuento (derivado)
                    $precioConDescuento = max(0.0, $precio - $descuentoUnitario);

                    // 4) Calcular a cuántas unidades/personas se aplicó el descuento para cuadrar con el total
                    $aplicaA = 0;

                    if ($descuentoUnitario > 0 && $cantidad > 0) {
                        // descuento total necesario para llegar al total objetivo
                        $descuentoTotalNecesario = $subtotalLinea - $totalObjetivo;

                        // estimación
                        $aplicaA = (int) round($descuentoTotalNecesario / $descuentoUnitario);

                        // clamp al rango [0, cantidad]
                        $aplicaA = max(0, min((int) round($cantidad), $aplicaA));

                        // ajuste fino (por floats/decimales)
                        $tries = 0;
                        while ($tries < 6) {
                            $totalCalc = (($cantidad - $aplicaA) * $precio) + ($aplicaA * $precioConDescuento);

                            if ($totalCalc > $totalObjetivo && $aplicaA < (int) round($cantidad)) {
                                $aplicaA++;
                            } elseif ($totalCalc < $totalObjetivo && $aplicaA > 0) {
                                $aplicaA--;
                            } else {
                                break;
                            }
                            $tries++;
                        }
                    }

                    // 5) Total calculado consistente
                    $totalCalculado = (($cantidad - $aplicaA) * $precio) + ($aplicaA * $precioConDescuento);

                    return [
                        'fecha' => $producto->created_at->format('Y-m-d H:i:s'),
                        'producto' => $producto->producto->descripcion,

                        // NO redondear precio/cantidad como regla de negocio
                        'precio' => round($precio, 2),
                        'cantidad' => round($cantidad, 2),

                        'porcentaje_descuento' => round($porcentaje, 2),

                        // ✅ descuento con regla
                        'descuento_unitario' => round($descuentoUnitario, 2),
                        'aplica_a' => $aplicaA,

                        'precio_con_descuento' => round($precioConDescuento, 2),

                        // total calculado consistente con aplica_a
                        'total' => round($totalCalculado, 2),

                        // si quieres ver también el objetivo para debug:
                        // 'total_objetivo' => round($totalObjetivo, 2),

                        'codigo_descuento' => $producto->descuentoEntity->codigo ?? 'N/A',
                    ];
                });

            return response()->json([
                'message' => 'Consulta realizada con exito',
                'productos' => $productos
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
