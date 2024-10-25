<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CorteRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class CorteCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CorteCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Corte::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/corte');
        CRUD::setEntityNameStrings('corte', 'cortes');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('apertura_id');
        CRUD::column('created_at');
        CRUD::column('deleted_at');
        CRUD::column('efectivo');
        CRUD::column('fecha_final');
        CRUD::column('fecha_inicio');
        CRUD::column('id');
        CRUD::column('sucursal_id');
        CRUD::column('tarjeta');
        CRUD::column('total');
        CRUD::column('total_caja');
        CRUD::column('transferencia');
        CRUD::column('updated_at');
        CRUD::column('user_id');

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']); 
         */
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
        CRUD::field('transferencia');
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
}
