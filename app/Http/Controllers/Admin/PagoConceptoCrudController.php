<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PagoConceptoRequest;
use App\Models\PagoConcepto;
use App\Models\Sucursal;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class PagoConceptoCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PagoConceptoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\PagoConcepto::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/pago-concepto');
        CRUD::setEntityNameStrings('pago concepto', 'pago conceptos');
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
                'name' => 'descripcion',
                'label' => 'Descripción',
                'type' => 'text',
            ],
            [
                'name' => 'sucursal_id',
                'label' => 'Sucursal',
                'type' => 'relationship',
                'attribute' => 'razon_social',
            ],
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
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PagoConceptoRequest::class);

        $this->crud->addFields([
            [
                'name' => 'descripcion',
                'label' => 'Descripción',
                'type' => 'text',
                'attributes' => [
                    'placeholder' => 'Descripción del concepto de pago',
                ],
            ],
            [
                'name' => 'sucursal_id',
                'label' => 'Sucursal',
                'type' => 'select2',
                'entity' => 'sucursal',
                'attribute' => 'razon_social',
                'model' => \App\Models\Sucursal::class,
            ],
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
        $search_term = $request->input('q');
        return PagoConcepto::query()->when($search_term, function ($query, $search_term) {
            return $query->where('descripcion', 'like', "%$search_term%");
        })->paginate(10);
    }
}
