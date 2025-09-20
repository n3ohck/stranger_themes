<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\VentaRequest;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaProducto;
use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Actions\VentaAction;
use Illuminate\Support\Facades\DB;

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
                'name' => 'codigo_descuento',
                'label' => 'Codigo Descuento',
                'type' => 'text'
            ],
            [
                'name' => 'porcentaje_descuento',
                'label' => 'Porcentaje Descuento',
                'type' => 'number',
                'decimals' => 2,
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
            $this->crud->addClause('where','user_id', $value);
        });

        $this->crud->addFilter([
            'name' => 'estatus',
            'type' => 'dropdown',
            'label' => 'Estatus'
        ], [
            'activo' => 'Activo',
            'cancelado' => 'Cancelado'
        ], function ($value) {
            $this->crud->addClause('where','estatus', $value);
        });

        $this->crud->addFilter([
            'type' => 'date_range',
            'name' => 'created_at',
            'label'=> 'Fecha'
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

        CRUD::field('codigo_descuento');
        CRUD::field('comentario_cancelacion');
        CRUD::field('created_at');
        CRUD::field('deleted_at');
        CRUD::field('descuento');
        CRUD::field('descuento_id');
        CRUD::field('estatus');
        CRUD::field('fecha_cancelacion');
        CRUD::field('folio');
        CRUD::field('id');
        CRUD::field('porcentaje_descuento');
        CRUD::field('sucursal_id');
        CRUD::field('total');
        CRUD::field('updated_at');
        CRUD::field('user_id');
        CRUD::field('user_id_cancelacion');

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */
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
            $ventas = Venta::query()
                ->search($search)
                ->with([
                    'productos' => function ($query) {
                        $query->with(['producto']);
                    },
                    'pagos',
                    'reservaciones' => function ($query) {
                        $query->with(['producto']);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json([
                'message' => 'Consulta realizada con exito',
                'ventas' => $ventas,
                'qty' => $ventas->count()
            ], 200);
        } catch (\Exception $e) {
            return response()
                ->json([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTrace()
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
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
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
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
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
            $params = $request->all();
            if (!isset($params['dates'])) {
                $params['dates'] = [
                    Carbon::now()->startOfDay()->format('Y-m-d H:i:s'),
                    Carbon::now()->endOfDay()->format('Y-m-d H:i:s')
                ];
            } else {
                $params['dates'][0] = Carbon::parse($params['dates'][0])->startOfDay()->format('Y-m-d H:i:s');
                $params['dates'][1] = Carbon::parse($params['dates'][1])->endOfDay()->format('Y-m-d H:i:s');
            }
            $totalVentas = 0;
            $cantidadReservaciones = 0;
            $totaEgresos = (new EgresoCrudController)->getTotal($params['dates']);
            $salarios = (new EmpleadoPagoCrudController)->getTotal($params['dates']);
            $ventas = Venta::query()
                ->with([
                    'user',
                    'sucursal',
                    'pagos'
                ])
                ->Filters($params)
                ->get()
                ->map(function ($venta) use (&$totalVentas) {
                    if ($venta->estatus === 'activo') {
                        $totalVentas += $venta->total;
                    }
                    return [
                        'folio' => $venta->folio,
                        'created_at' => $venta->created_at->format('Y-m-d H:i:s'),
                        'tarjeta' => $venta->pagos->where('tipo', 'tarjeta')->sum('monto'),
                        'efectivo' => $venta->pagos->where('tipo', 'efectivo')->sum('monto'),
                        'online' => $venta->pagos->where('tipo', 'online')->sum('monto'),
                        'descuento' => $venta->descuento ?? 0,
                        'total' => $venta->total,
                        'cambio' => $venta->pagos->sum('cambio'),
                        'estatus' => $venta->estatus,
                        'sucursal' => $venta->sucursal->razon_social,
                        'codigo_descuento' => $venta->codigo_descuento ?? 'N/A',
                    ];
                });

            $cantidadReservaciones = Reserva::query()
                ->where(function ($q) use ($params) {
                    $q
                        ->where('estado', 'confirmada')
                        ->whereBetween('fecha', [$params['dates'][0], $params['dates'][1]]);
                })
                ->count();

            $utilidad_operativa = $totalVentas - ($totaEgresos + $salarios);
            return response()->json([
                'message' => 'Consulta realizada con exito',
                'ventas' => $ventas,
                'cantidad_ventas' => $ventas->count(),
                'cantidad_reservaciones' => $cantidadReservaciones,
                'total_ventas' => number_format($totalVentas, 2, '.', ','),
                'total_egresos' => number_format($totaEgresos, 2, '.', ','),
                'total_salarios' => number_format($salarios, 2, '.', ','),
                'utilidad_operativa' => number_format($utilidad_operativa, 2, '.', ',')
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resumenProductos(Request $request)
    {
        try {
            $params = $request->all();
            if (!isset($params['dates'])) {
                $params['dates'] = [
                    Carbon::now()->startOfDay()->format('Y-m-d'),
                    Carbon::now()->endOfDay()->format('Y-m-d')
                ];
            }

            $productos = VentaProducto::query()
                ->whereHas('venta', function ($query) use ($params) {
                    $query->Filters($params);
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
            if (!isset($params['dates'])) {
                $params['dates'] = [
                    Carbon::now()->startOfDay()->format('Y-m-d'),
                    Carbon::now()->endOfDay()->format('Y-m-d')
                ];
            }

            $productos = VentaProducto::query()
                ->whereHas('venta', function ($query) use ($params) {
                    $query->Filters($params);
                })
                ->with([
                    'venta.pagos',
                    'producto',
                    'descuento'
                ])
                ->where('descuento', '>', 0)
                ->get()
                ->map(function ($producto) {
                    $paymentsOnline = $producto->venta->pagos->where('tipo', 'online')->count();
                    if ($paymentsOnline){
                        $totalOrginal = $producto->total;
                        $producto->total = $producto->descuento;
                        $producto->descuento = $totalOrginal - $producto->descuento;
                    }
                    //dd($paymentsOnline,$producto->precio, $producto->descuento, $producto->porcentaje_descuento,$producto->total);
                    return [
                        'fecha' => $producto->created_at->format('Y-m-d H:i:s'),
                        'producto' => $producto->producto->descripcion,
                        'precio' => $producto->precio,
                        'descuento' => $producto->descuento,
                        'porcentaje_descuento' => $producto->porcentaje_descuento,
                        'codigo_descuento' => $producto->codigo_descuento ?? '-',
                        'total' => $producto->total
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
}
