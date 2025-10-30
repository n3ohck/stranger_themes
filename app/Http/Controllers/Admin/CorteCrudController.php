<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CorteRequest;
use App\Models\Apertura;
use App\Models\Corte;
use App\Models\Egreso;
use App\Models\EmpleadoPago;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prologue\Alerts\Facades\Alert;

/**
 * Class CorteCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CorteCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \App\Traits\DateTrait;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Corte::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/corte');
        CRUD::setEntityNameStrings('corte', 'cortes');
        if (!backpack_user()->can('cortes.ver')) {
            Alert::warning('No tienes permisos para ver los cortes')->flash();
            $this->crud->denyAccess('list');
        }

        if (!backpack_user()->can('cortes.eliminar')) {
            $this->crud->denyAccess('delete');
        }
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
                'name' => 'apertura_id',
                'label' => 'Fondo inicial',
                'type' => 'select',
                'entity' => 'apertura',
                'attribute' => 'monto_apertura',
                'model' => Apertura::class
            ],
            [
                'name' => 'fondo_final',
                'label' => 'Fondo Final',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'total',
                'label' => 'Total Venta',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'efectivo',
                'label' => 'Efectivo',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'tarjeta',
                'label' => 'Tarjeta',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'total_online',
                'label' => 'Online',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'efectivo_fondo',
                'label' => 'Efectivo + Fondo',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'pago_empleados',
                'label' => 'Pago Empleados',
                'type' => 'closure',
                'function' => function ($entry) {
                    $pago_empleados = number_format($entry->pago_empleados, 2, '.', ',');
                    return '<a href="payment-report/' . $entry->id . '/empleado" target="_blank" title="Ver imagenes de pagos empleados">' . $pago_empleados . '</a>';
                }
            ],
            [
                'name' => 'total_egresos',
                'label' => 'Total Egresos',
                'type' => 'closure',
                'function' => function ($entry) {
                    $total_egresos = number_format($entry->total_egresos, 2, '.', ',');
                    return '<a href="payment-report/' . $entry->id . '/egreso" target="_blank" title="Ver imagenes de egresos">' . $total_egresos . '</a>';
                }
            ],
            [
                'name' => 'total_caja',
                'label' => 'Total en caja efectivo',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'ganancia',
                'label' => 'Ganancia',
                'type' => 'number',
                'decimals' => 2
            ],
            [
                'name' => 'fecha_inicio',
                'label' => 'Fecha Inicio',
                'type' => 'closure',
                'function' => function ($entry) {
                    return $entry->fecha_inicio ? Carbon::parse($entry->fecha_inicio)->locale('es')->translatedFormat('l, d \d\e F H:i:s') : '';
                }
            ],
            [
                'name' => 'fecha_final',
                'label' => 'Fecha Final',
                'type' => 'closure',
                'function' => function ($entry) {
                    return $entry->fecha_final ? Carbon::parse($entry->fecha_final)->locale('es')->translatedFormat('l, d \d\e F H:i:s') : '';
                }
            ],
            [
                'name' => 'user_id',
                'label' => 'Usuario',
                'type' => 'select',
                'entity' => 'user',
                'attribute' => 'nombre_completo',
                'model' => User::class
            ]
        ]);

        $this->crud->addFilter([ // dropdown filter
            'name' => 'sucursal_id',
            'type' => 'dropdown',
            'label' => 'Sucursal'
        ], Sucursal::query()
            ->select('id', 'razon_social')
            ->get()
            ->pluck('razon_social', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'sucursal_id', $value);
        });

        $this->crud->addFilter([ // dropdown filter
            'name' => 'user_id',
            'type' => 'dropdown',
            'label' => 'Usuario'
        ], User::query()
            ->select('id', DB::raw('CONCAT(first_name, " ", last_name) as full_name'))
            ->get()
            ->pluck('full_name', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'user_id', $value);
        });

        $this->crud->addFilter([ // daterange filter
            'type' => 'date_range',
            'name' => 'fecha_inicio',
            'label' => 'Fecha de inicio'
        ],
            false,
            function ($value) { // if the filter is active, apply these constraints
                $dates = json_decode($value);
                $datesFrom = \Carbon\Carbon::parse($dates->from)->startOfDay();
                $datesTo = \Carbon\Carbon::parse($dates->to)->endOfDay();
                $this->crud->addClause('where', 'fecha_inicio', '>=', $datesFrom);
                $this->crud->addClause('where', 'fecha_inicio', '<=', $datesTo);
            });

        $this->crud->addFilter([ // daterange filter
            'type' => 'date_range',
            'name' => 'fecha_final',
            'label' => 'Fecha de final'
        ],
            false,
            function ($value) { // if the filter is active, apply these constraints
                $dates = json_decode($value);
                $datesFrom = \Carbon\Carbon::parse($dates->from)->startOfDay();
                $datesTo = \Carbon\Carbon::parse($dates->to)->endOfDay();
                $this->crud->addClause('where', 'fecha_final', '>=', $datesFrom);
                $this->crud->addClause('where', 'fecha_final', '<=', $datesTo);
            });

        $this->crud->enableExportButtons();
        $this->crud->removeButton('create');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(CorteRequest::class);

        CRUD::field('apertura_id');
        CRUD::field('created_at');
        CRUD::field('deleted_at');
        CRUD::field('efectivo');
        CRUD::field('fecha_final');
        CRUD::field('fecha_inicio');
        CRUD::field('id');
        CRUD::field('sucursal_id');
        CRUD::field('tarjeta');
        CRUD::field('total');
        CRUD::field('total_caja');
        CRUD::field('updated_at');
        CRUD::field('user_id');

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
                'fecha_inicio' => $this->makeDate($request->get('fecha_inicio')),
                'fecha_final' => $this->makeDate($request->get('fecha_final')),
                'user_id' => $request->get('user_id'),
                'apertura_id' => $request->get('apertura_id'),
            ];

            $cortes = Corte::query()
                ->Search($search)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Consulta realizada con exito',
                'cortes' => $cortes,
                'qty' => $cortes->count()
            ], 200);
        } catch (\Exception $e) {
            return response()
                ->json([
                    'error' => $e->getMessage()
                ], 500);
        }
    }

    public function validateRequest($request)
    {
        $aperturaId = $request->get('apertura_id');
        if (!$aperturaId) throw new \Exception('Debe seleccionar una apertura');
        $apertura = Apertura::find($aperturaId);
        if (!$apertura) throw new \Exception('Apertura no encontrada');

        if (!$request->get('fecha_inicio') || !$request->get('fecha_final')) {
            throw new \Exception('Debe seleccionar una fecha de inicio y una fecha final');
        }

        if ($request->get('total') < 0) {
            throw new \Exception('El total debe ser mayor a 0');
        }

        if ($apertura->user_id_cerro) {
            throw new \Exception('La apertura ya fue cerrada');
        }

        $cortes = Corte::query()->where('apertura_id', $aperturaId)->exists();
        if ($cortes) {
            throw new \Exception('Ya se realizo un corte para esta apertura');
        }

        return $apertura;
    }

    public function make(Request $request)
    {
        try {
            $apertura = $this->validateRequest($request);
            DB::beginTransaction();
            $corte = Corte::create([
                'total' => $request->get('total'),
                'efectivo' => $request->get('efectivo'),
                'tarjeta' => $request->get('tarjeta'),
                'transferencia' => $request->get('transferencia'),
                'total_online' => $request->get('online'),
                'total_caja' => $request->get('total_caja'),
                'fecha_inicio' => $this->makeDate($request->get('fecha_inicio')),
                'fecha_final' => $this->makeDate($request->get('fecha_final')),
                'user_id' => backpack_user()->id,
                'sucursal_id' => backpack_user()->sucursal_id,
                'apertura_id' => $apertura->id,
                'fondo_final' => $request->get('fondo_final') ?? 0,
            ]);
            $apertura->estado = 'cerrado';
            $apertura->user_id_cerro = backpack_user()->id;
            $apertura->monto_cierre = $corte->total_caja;
            $apertura->save();
            DB::commit();
            return response()->json([
                'message' => 'Corte realizado con exito',
                'corte' => $corte
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()
                ->json([
                    'error' => $e->getMessage()
                ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $corte = Corte::where('id',$id);
            if (!$corte->exists()) {
                throw new \Exception('Corte no encontrado o no tienes permisos para eliminarlo');
            }
            $corte = $corte->first();
            $fecha_inicio = Carbon::parse($corte->fecha_inicio)->startOfDay();
            $fecha_final = Carbon::parse($corte->fecha_final) ?? Carbon::now();
            $fecha_final = $fecha_final->endOfDay();
            DB::beginTransaction();
            $corte->delete();

            Venta::where('user_id', $corte->user_id)
                ->whereBetween('created_at', [$fecha_inicio, $fecha_final])
                ->with([
                    'productos', 'pagos', 'reservaciones'
                ])
                ->where('sucursal_id', $corte->sucursal_id)
                ->chunk(100, function ($ventas) {
                    foreach ($ventas as $venta) {
                        if ($venta->productos) {
                            $venta->productos()->delete();
                        }
                        $venta->pagos()->delete();
                        if ($venta->reservaciones) {
                            $venta->reservaciones->each(function ($reserva) {
                                $reserva->estado = 'cancelada';
                                $reserva->save();
                            });
                            $venta->reservaciones()->delete();
                        }
                        $venta->delete();
                    }
                });

            EmpleadoPago::where('user_id', $corte->user_id)
                ->whereBetween('created_at', [$fecha_inicio, $fecha_final])
                ->delete();

            Egreso::where('user_id', $corte->user_id)
                ->whereBetween('fecha_pago', [$fecha_inicio, $fecha_final])
                ->delete();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()
                ->json([
                    'error' => $e->getMessage()
                ], 500);
        }
    }

}
