<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\EgresoRequest;
use App\Models\Egreso;
use App\Models\Sucursal;
use App\Models\User;
use App\Traits\DateTrait;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class EgresoCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class EgresoCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Egreso::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/egreso');
        CRUD::setEntityNameStrings('egreso', 'egresos');
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
                'name' => 'user_id',
                'type' => 'relationship',
                'label' => 'Usuario',
                'attribute' => 'nombre_completo',
            ],
            [
                'name' => 'sucursal_id',
                'type' => 'relationship',
                'label' => 'Sucursal',
                'attribute' => 'razon_social',
            ],
            [
                'name' => 'monto',
                'type' => 'number',
                'label' => 'Importe',
                'prefix' => "$",
                'decimals' => 2,
            ],
            [
                'name' => 'tipo_pago',
                'type' => 'enum',
                'label' => 'Metodo de pago',
            ],
            [
                'name' => 'referencia',
                'type' => 'text',
                'label' => 'Referencia',
            ],
            [
                'name' => 'fecha_pago',
                'type' => 'date',
                'label' => 'Fecha de pago',
            ],
            [
                'name' => 'estatus',
                'type' => 'enum',
                'label' => 'Estatus',
            ],
            [
                'name' => 'descripcion',
                'type' => 'text',
                'label' => 'Descripcion',
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
            'name' => 'fecha_pago',
            'label' => 'Fecha de pago'
        ],
            false,
            function ($value) { // if the filter is active, apply these constraints
                $dates = json_decode($value);
                $datesFrom = \Carbon\Carbon::parse($dates->from)->startOfDay();
                $datesTo = \Carbon\Carbon::parse($dates->to)->endOfDay();
                $this->crud->addClause('where', 'fecha_pago', '>=', $datesFrom);
                $this->crud->addClause('where', 'fecha_pago', '<=', $datesTo);
            });

        $this->crud->addFilter([ // dropdown filter
            'name' => 'estatus',
            'type' => 'dropdown',
            'label' => 'Estatus'
        ], ['activo' => 'Activo', 'inactivo' => 'Inactivo'], function ($value) { // if the filter is active
            $this->crud->addClause('where', 'estatus', $value);
        });

        $this->crud->addButtonFromModelFunction('line', 'fileButton', 'fileButton', 'beginning');
        $this->crud->enableExportButtons();
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(EgresoRequest::class);
        request()->merge(['sucursal_id' => backpack_user()->sucursal_id]);
        $this->crud->addFields([
            [   // 1-n relationship
                'label' => "Usuario que realizo el egreso", // Table column heading
                'type' => "select2_from_ajax",
                'name' => 'user_id', // the column that contains the ID of that connected entity
                'entity' => 'user', // the method that defines the relationship in your Model
                'attribute' => "nombre_completo", // foreign key attribute that is shown to user
                'data_source' => url("webapi/users"), // url to controller search function (with /{id} should return model)
                'delay' => 0, // the minimum amount of time between ajax requests when searching in the field
                'placeholder' => "Seleccione usuario", // placeholder for the select
                'minimum_input_length' => 0, // minimum characters to type before querying results
                'model' => User::class, // foreign key model
                'method' => 'GET', // optional - HTTP method to use for the AJAX call (GET, POST)
            ],
            [
                'name' => 'monto',
                'type' => 'number',
                'label' => 'Importe',
                'placeholder' => 'Importe',
                'hint' => 'Es el importe total del pago',
                'attributes' => ["step" => "any"], // allow decimals
                'prefix' => "$",
                'wrapper' => [
                    'class' => 'form-group col-md-4'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'label' => 'Metodo de pago',
                'name' => 'tipo_pago',
                'type' => 'enum',
                'hint' => 'Metodo de pago.',
                'wrapper' => [
                    'class' => 'form-group col-md-4'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'label' => 'Referencia',
                'name' => 'referencia',
                'placeholder' => 'Numero de ticket',
                'type' => 'text',
                'hint' => 'Eje. Numero de ticket',
                'wrapper' => [
                    'class' => 'form-group col-md-4'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'fecha_pago',
                'type' => 'date',
            ],
            [   // Textarea
                'name'  => 'descripcion',
                'label' => 'Descripcion',
                'type'  => 'textarea'
            ],
            [   // Upload
                'name'      => 'imagen',
                'label'     => 'Comprobante',
                'type'      => 'upload',
                'upload'    => true,
                'disk'      => 'pagos', // if you store files in the /public folder, please omit this; if you store them in /storage or S3, please specify it;
            ],
            [
                'name' => 'estatus',
                'type' => 'enum',
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
        try {
            $request->validate([
                'monto' => 'required|numeric',
                'descripcion' => 'required|string',
                'tipo_pago' => 'required|string',
                'referencia' => 'required|string',
                'fecha_pago' => 'required',
                'imagen' => 'required|file',
            ]);
            DB::beginTransaction();
            $egreso = Egreso::create([
                'user_id' => backpack_user()->id,
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'tipo_pago' => $request->tipo_pago,
                'estatus' => 'activo',
                'referencia' => $request->referencia,
                'imagen' => $request->file('imagen')->store('egresos', 'pagos'),
                'fecha_pago' => $this->makeDate($request->fecha_pago),
                'sucursal_id' => backpack_user()->sucursal_id
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Egreso creado con exito',
                'egreso' => $egreso
            ], 200);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 500);
        }
    }

    public function fetch(Request $request)
    {
        try{
            $search = (object)[
                'user_id' => $request->get('user_id'),
                'sucursal_id' => $request->get('sucursal_id'),
                'fecha_pago' => $request->get('fecha_pago'),
                'estatus' => $request->get('estatus'),
                'tipo_pago' => $request->get('tipo_pago')
            ];
            $egresos = Egreso::query()
                ->Search($search)
                ->orderBy('fecha_pago', 'desc')
                ->get();
            return response()->json([
                'message' => 'Egresos encontrados',
                'egresos' => $egresos,
                'qty' => $egresos->count()
            ], 200);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 500);
        }
    }

}
