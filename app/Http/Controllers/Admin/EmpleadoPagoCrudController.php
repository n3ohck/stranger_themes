<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\EmpleadoPagoRequest;
use App\Models\Empleado;
use App\Models\EmpleadoPago;
use App\Models\Sucursal;
use App\Traits\DateTrait;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class EmpleadoPagoCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class EmpleadoPagoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use DateTrait;
    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\EmpleadoPago::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/empleado-pago');
        CRUD::setEntityNameStrings('empleado pago', 'empleado pagos');
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
                'name' => 'empleado_id',
                'type' => 'relationship',
                'label' => 'Empleado',
                'attribute' => 'nombre_completo',
                'entity' => 'empleado',
                'model' => \App\Models\Empleado::class
            ],
            [
                'name' => 'fecha_pago',
                'type' => 'date',
                'label' => 'Fecha de pago'
            ],
            [
                'name' => 'monto',
                'type' => 'number',
                'label' => 'Monto',
                'decimals' => 2
            ]
        ]);

        $this->crud->removeButton('create');
        $this->crud->enableExportButtons();
        $this->crud->addFilter([ // dropdown filter
            'name' => 'empleado_id',
            'type' => 'dropdown',
            'label' => 'Empleado'
        ], Empleado::query()
            ->select('id','nombres','apellidos')
            ->get()
            ->pluck('nombre_completo', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'empleado_id', $value);
        });

        $this->crud->addFilter([ // dropdown filter
            'name' => 'id',
            'type' => 'dropdown',
            'label' => 'Sucursal'
        ], Sucursal::query()
            ->select('id','razon_social')
            ->get()
            ->pluck('razon_social', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('BySucursal', $value);
        });
        $this->crud->addButtonFromModelFunction('line', 'fileButton', 'fileButton', 'beginning');

    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(EmpleadoPagoRequest::class);

        $this->crud->addFields([
            [
                'name' => 'empleado_id',
                'type' => 'select2',
                'label' => 'Empleado',
                'entity' => 'empleado',
                'attribute' => 'nombre_completo',
                'model' => Empleado::class
            ],
            [
                'name' => 'fecha_pago',
                'type' => 'date',
                'label' => 'Fecha de pago'
            ],
            [   // Upload
                'name'      => 'imagen',
                'label'     => 'Comprobante',
                'type'      => 'upload',
                'upload'    => true,
                'disk'      => 'pagos', // if you store files in the /public folder, please omit this; if you store them in /storage or S3, please specify it;
            ],
            [
                'name' => 'monto',
                'type' => 'number',
                'label' => 'Monto',
                'decimals' => 2
            ]
        ]);
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

    public function make(Request $request)
    {
        try{
            DB::beginTransaction();
            $pago = EmpleadoPago::create([
                'empleado_id' => $request->empleado_id,
                'fecha_pago' => $this->makeDate($request->fecha_pago),
                'imagen' => $request->file('imagen')->store('egresos', 'pagos'),
                'monto' => $request->monto
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Pago registrado correctamente',
                'data' => $pago
            ], 201);
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $search = (object)[
                'empleado_id' => $request->get('empleado_id'),
                'fecha_pago' => $this->makeDate($request->get('fecha_pago')),
                'estatus' => $request->get('estatus')
            ];
            $pagos = EmpleadoPago::query()
                ->Search($search)
                ->orderBy('fecha_pago', 'desc')
                ->get();
            return response()->json([
                'message' => 'Pagos obtenidos correctamente',
                'data' => $pagos
            ],200);
        }catch (\Exception $e){
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 500);
        }
    }

    public function getTotal($dates)
    {
        return EmpleadoPago::query()
            ->whereBetween('fecha_pago', $dates)
            ->where('estatus','activo')
            ->sum('monto');
    }
}
