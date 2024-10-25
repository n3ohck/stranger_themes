<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AperturaRequest;
use App\Models\Apertura;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class AperturaCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class AperturaCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Apertura::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/apertura');
        CRUD::setEntityNameStrings('apertura', 'aperturas');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('billetes');
        CRUD::column('created_at');
        CRUD::column('deleted_at');
        CRUD::column('estado');
        CRUD::column('id');
        CRUD::column('monto_apertura');
        CRUD::column('monto_cierre');
        CRUD::column('updated_at');
        CRUD::column('user_id');
        CRUD::column('user_id_cerro');

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
        CRUD::setValidation(AperturaRequest::class);

        CRUD::field('billetes');
        CRUD::field('created_at');
        CRUD::field('deleted_at');
        CRUD::field('estado');
        CRUD::field('id');
        CRUD::field('monto_apertura');
        CRUD::field('monto_cierre');
        CRUD::field('updated_at');
        CRUD::field('user_id');
        CRUD::field('user_id_cerro');

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
                'user_id' => $request->get('apertura_user_id'),
                'user_id_cerro' => $request->get('apertura_user_id_cerro'),
                'estado' => $request->get('apertura_estado'),
                'fecha_apertura' => $request->get('fecha_apertura')
            ];
            $aperturas = Apertura::query()
                ->Search($search)
                ->orderBy('created_at', 'desc')
                ->get();
        }catch (\Exception $e){
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ], 500);
        }
    }
}
