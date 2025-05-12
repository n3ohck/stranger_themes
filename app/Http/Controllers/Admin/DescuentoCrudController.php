<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DescuentoRequest;
use App\Models\Descuento;
use App\Models\Sucursal;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

/**
 * Class DescuentoCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DescuentoCrudController extends CrudController
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
        if( !backpack_user() ){
            \Auth::loginUsingId(1);
        }
        CRUD::setModel(\App\Models\Descuento::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/descuento');
        CRUD::setEntityNameStrings('descuento', 'descuentos');
        if( !backpack_user()->can('descuentos.ver') ){
            Alert::warning('No tienes permisos para ver los descuentos')->flash();
            $this->crud->denyAccess('list');
        }

        if( !backpack_user()->can('descuentos.crear') ){
            $this->crud->denyAccess('create');
        }

        if( !backpack_user()->can('descuentos.editar') ){
            $this->crud->denyAccess('update');
        }

        if( !backpack_user()->can('descuentos.eliminar') ){
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
               'name' => 'codigo',
               'label' => 'Código'
           ],
           [
               'name' => 'porcentaje',
               'label' => 'Porcentaje'
           ],
           [
               'name' => 'producto_tipo',
                'label' => 'Tipo de Producto'
           ],
           [
               'name' => 'sucursal_id',
               'label' => 'Sucursal',
               'type' => 'relationship',
               'attribute' => 'razon_social',
               'entity' => 'sucursal',
               'model' => \App\Models\Sucursal::class
           ],
           [
               'name' => 'estatus',
               'label' => 'Estatus'
           ]
       ]);

        $this->crud->addFilter([ // dropdown filter
            'name' => 'sucursal_id',
            'type' => 'dropdown',
            'label' => 'Sucursal'
        ],Sucursal::query()
            ->select('id','razon_social')
            ->get()
            ->pluck('razon_social','id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'sucursal_id', $value);
        });

        $this->crud->addFilter([ // dropdown filter
            'name' => 'producto_tipo',
            'type' => 'dropdown',
            'label' => 'Producto Tipo'
        ],['tour' => 'Tour','articulo' => 'Articulo','tour_paquete' => 'Paquete'], function ($value) { // if the filter is active
            $this->crud->addClause('where', 'producto_tipo', $value);
        });

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
        CRUD::setValidation(DescuentoRequest::class);

        $this->crud->addFields([
            [
                'name' => 'codigo',
                'label' => 'Código',
                'type' => 'text'
            ],
            [
                'name' => 'porcentaje',
                'label' => 'Porcentaje',
                'type' => 'number',
                'attributes' => [
                    'step' => '0.01'
                ]
            ],
            [
                'name' => 'producto_tipo',
                'label' => 'Tipo de Producto',
                'type' => 'enum'
            ],
            [
                'name' => 'sucursal_id',
                'label' => 'Sucursal',
                'type' => 'select2',
                'entity' => 'sucursal',
                'attribute' => 'razon_social',
                'model' => \App\Models\Sucursal::class
            ],
            [
                'name' => 'estatus',
                'label' => 'Estatus',
                'type' => 'select_from_array',
                'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
                'allows_null' => false
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
            $estatus = $request->get('estatus');
            $sucursalId = $request->get('sucursal_id');
            $descuentos = Descuento::query()
                ->FilterByEstatus($estatus,$sucursalId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['descuentos' => $descuentos, 'qty' => $descuentos->count()], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function publicFetch(Request $request)
    {
        try {
            $sucursalId = $request->get('sucursal_id');
            $codigo = $request->get('codigo');

            if (!$codigo) {
                throw new \Exception("No se encontro el codigo");
            }
            if(!$sucursalId ){
                throw new \Exception("No se encontro la sucursal");
            }

            $descuento = Descuento::query()
                ->select([
                    'id',
                    'codigo',
                    'porcentaje',
                    'sucursal_id',
                    'producto_tipo',
                    'estatus'
                ])
                ->where(function($q)use($codigo,$sucursalId){
                    $q->where('codigo', "$codigo")
                        ->where('sucursal_id', $sucursalId)
                        ->where('estatus', 'activo');
                })
                ->first();

            return response()->json(['descuentos' => $descuento, 'valid' => (isset($descuento->id))], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
