<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use App\Models\Sucursal;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class ProductoCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductoCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Producto::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/producto');
        CRUD::setEntityNameStrings('producto', 'productos');
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
                'type' => 'text',
                'label' => 'Código'
            ],
            [
                'name' => 'descripcion',
                'type' => 'text',
                'label' => 'Descripción'
            ],
            [
                'name' => 'precio',
                'type' => 'text',
                'label' => 'Precio'
            ],
            [
                'name' => 'existencia',
                'type' => 'text',
                'label' => 'Existencia'
            ],
            [
                'name' => 'tipo',
                'type' => 'text',
                'label' => 'Tipo'
            ],
            [
                'name' => 'sucursal_id',
                'type' => 'relationship',
                'label' => 'Sucursal',
                'attribute' => 'razon_social',
            ],
        ]);

        $this->crud->addFilter([ // dropdown filter
            'name' => 'tipo',
            'type' => 'dropdown',
            'label' => 'Tipo'
        ], [
            'tour' => 'Tour',
            'tour_paquete' => 'Paquete',
            'articulo' => 'Articulo / Producto',
        ], function ($value) { // if the filter is active
            $this->crud->addClause('where', 'tipo', $value);
        });

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
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ProductoRequest::class);
        $this->crud->addFields([
            [
                'name' => 'codigo',
                'type' => 'text',
                'label' => 'Código',
                'hint'       => 'Codigo interno del producto', // helpful text, shows up after the input
                'attributes' => [
                    'placeholder' => 'Codigo interno del producto',
                    'class'       => 'form-control'
                ], // change the HTML attributes of your input
                'wrapper'   => [
                    'class'      => 'form-group col-md-4'
                ], // change the HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'descripcion',
                'type' => 'text',
                'label' => 'Descripción',
                'hint'       => 'Descripcion y/o nombre del producto', // helpful text, shows up after the input
                'attributes' => [
                    'placeholder' => 'Descripcion y/o nombre del producto',
                    'class'       => 'form-control'
                ], // change the HTML attributes of your input
                'wrapper'   => [
                    'class'      => 'form-group col-md-4'
                ], // change the HTML attributes for the field wrapper - mostly for resizing fields

            ],
            [
                'name' => 'precio',
                'type' => 'number',
                'label' => 'Precio del producto',
                'attributes' => [
                    'placeholder' => 'Precio del producto',
                    'class'       => 'form-control',
                    'step'        => '0.01'
                ], // change the HTML attributes of your input
                'wrapper'   => [
                    'class'      => 'form-group col-md-4'
                ], // change the HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'existencia',
                'type' => 'number',
                'label' => 'Existencia total del producto',
                'attributes' => [
                    'placeholder' => 'Existencia total del producto',
                    'class'       => 'form-control',
                    'step'        => '0.01'
                ], // change the HTML attributes of your input
                'wrapper'   => [
                    'class'      => 'form-group col-md-6'
                ], // change the HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'tipo',
                'type' => 'enum',
                'label' => 'Tipo de producto',
                'attributes' => [
                    'placeholder' => 'Tipo de producto',
                    'class'       => 'form-control'
                ], // change the HTML attributes of your input
                'wrapper'   => [
                    'class'      => 'form-group col-md-6'
                ], // change the HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [   // repeatable
                'name'  => 'tours',
                'label' => 'Agregar Tours al producto (Paqute de tours)',
                'type'  => 'repeatable',
                'fields' => [
                    [
                        'name'        => 'producto_id',
                        'label'       => "Tour",
                        'type'        => 'select2_from_array',
                        'options'     => Producto::query()
                            ->where('tipo','tour')
                            ->get()
                            ->pluck('descripcion','id')
                            ->toArray(),
                        'allows_null' => false,
                        'wrapper' => ['class' => 'form-group col-md-12'],
                    ]
                ],
                'new_item_label'  => 'Añadir',
                'init_rows' => 0,
                'min_rows' => 1,
                'max_rows' => 7,
            ],
            [
                'name' => 'sucursal_id',
                'type' => 'select2',
                'label' => 'Sucursal',
                'entity' => 'sucursal',
                'attribute' => 'razon_social',
                'model' => \App\Models\Sucursal::class,
                'attributes' => [
                    'placeholder' => 'Sucursal',
                    'class'       => 'form-control'
                ], // change the HTML attributes of your input
                'wrapper'   => [
                    'class'      => 'form-group col-md-12'
                ], // change the HTML attributes for the field wrapper - mostly for resizing fields
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
        try{
            $tipo = $request->get('tipo');
            $productos = Producto::query()
                ->FilterByType($tipo)
                ->where('existencia', '>', 0)
                ->orderBy('descripcion','asc')
                ->get();

            return response()->json([
                'productos' => $productos,
                'qty' => $productos->count()
            ],200);
        }catch (\Exception $e){
            return response()->json(['error' => $e->getMessage()],500);
        }
    }
}
