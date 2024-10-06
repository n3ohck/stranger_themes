<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\VentaRequest;
use App\Models\Venta;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
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
        CRUD::column('codigo_descuento');
        CRUD::column('comentario_cancelacion');
        CRUD::column('created_at');
        CRUD::column('deleted_at');
        CRUD::column('descuento');
        CRUD::column('descuento_id');
        CRUD::column('estatus');
        CRUD::column('fecha_cancelacion');
        CRUD::column('folio');
        CRUD::column('id');
        CRUD::column('porcentaje_descuento');
        CRUD::column('sucursal_id');
        CRUD::column('total');
        CRUD::column('updated_at');
        CRUD::column('user_id');
        CRUD::column('user_id_cancelacion');

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

    public function make(Request $request)
    {
        try {
            if( !$request->has('ventas') ) throw new \Exception('No se han enviado ventas');
            $ventas = $request->input('ventas');
            DB::beginTransaction();
            foreach ($ventas as $venta){
                if( !isset($venta['productos']) ) throw new \Exception('No se han enviado productos');
                if( !isset($venta['pagos']) ) throw new \Exception('No se han enviado pagos');
            }
            $ventas = (new VentaAction())->do($request->ventas);
            DB::commit();
            return response()->json(['ventas' => $ventas, 'qty' => count($ventas)], 200);
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request)
    {
        try {
            if( !$request->has('ventas') ) throw new \Exception('No se ha enviado las ventas a cancelar');
            $ventasCancelar = $request->input('ventas');
            DB::beginTransaction();
            $ventas = (new VentaAction())->cancelVentas($ventasCancelar);
            DB::commit();
            return response()->json(['ventas' => $ventas, 'qty' => count($ventas)], 200);
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
