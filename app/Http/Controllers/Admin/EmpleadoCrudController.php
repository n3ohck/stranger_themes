<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\EmpleadoRequest;
use App\Models\Empleado;
use App\Models\Sucursal;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class EmpleadoCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class EmpleadoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Empleado::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/empleado');
        CRUD::setEntityNameStrings('empleado', 'empleados');
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
                'name' => 'nombres',
                'type' => 'text',
                'label' => 'Nombres'
            ],
            [
                'name' => 'apellidos',
                'type' => 'text',
                'label' => 'Apellidos'
            ],
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email'
            ],
            [
                'name' => 'telefono',
                'type' => 'text',
                'label' => 'Teléfono'
            ],
            [
                'name' => 'estatus',
                'type' => 'text',
                'label' => 'Estatus'
            ],
            [
                'name' => 'salario',
                'type' => 'text',
                'label' => 'Salario'
            ],
            [
                'name' => 'sucursal_id',
                'type' => 'relationship',
                'label' => 'Sucursal',
                'attribute' => 'razon_social',
                'entity' => 'sucursal',
                'model' => \App\Models\Sucursal::class
            ]
        ]);
        $this->crud->enableExportButtons();
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
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(EmpleadoRequest::class);

        $this->crud->addFields([
            [
                'name' => 'nombres',
                'type' => 'text',
                'label' => 'Nombres'
            ],
            [
                'name' => 'apellidos',
                'type' => 'text',
                'label' => 'Apellidos'
            ],
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email'
            ],
            [
                'name' => 'telefono',
                'type' => 'text',
                'label' => 'Teléfono'
            ],
            [
                'name' => 'salario',
                'type' => 'number',
                'label' => 'Salario',
                'attributes' => [
                    'step' => '0.01'
                ]
            ],
            [   // select2_from_array
                'name' => 'sucursal_id',
                'label' => "Sucursal",
                'type' => 'select2_from_array',
                'options' => Sucursal::query()->select(['id', 'razon_social'])->pluck('razon_social', 'id')->toArray(),
                'allows_null' => false,
            ],
            [
                'name' => 'estatus',
                'type' => 'enum',
                'label' => 'Estatus'
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

    public function fetch(Request $request)
    {
        try {
            $search = (object)[
                'nombres' => $request->get('nombres'),
                'apellidos' => $request->get('apellidos'),
                'sucursal_id' => $request->get('sucursal_id'),
                'estatus' => $request->get('estatus')
            ];
            $empleados = Empleado::query()
                ->Search($search)
                ->orderBy('nombres')
                ->get();
            return response()->json([
                'message' => 'Empleados encontrados',
                'empleados' => $empleados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
