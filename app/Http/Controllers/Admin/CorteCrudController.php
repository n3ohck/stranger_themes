<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CorteRequest;
use App\Models\Corte;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        }catch (\Exception $e) {
            return response()
                ->json([
                    'error' => $e->getMessage()
                ], 500);
        }
    }

    public function validateRequest($request)
    {
        $aperturaId = $request->get('apertura_id');
        if( !$aperturaId ) throw new \Exception('Debe seleccionar una apertura');
        $apertura = Apertura::find($aperturaId);
        if(!$apertura) throw new \Exception('Apertura no encontrada');

        if (!$request->get('fecha_inicio') || !$request->get('fecha_final')) {
            throw new \Exception('Debe seleccionar una fecha de inicio y una fecha final');
        }

        if ($request->get('total') <= 0) {
            throw new \Exception('El total debe ser mayor a 0');
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
                'total_caja' => $request->get('total_caja'),
                'fecha_inicio' => $this->makeDate($request->get('fecha_inicio')),
                'fecha_final' => $this->makeDate($request->get('fecha_final')),
                'user_id' => backpack_user()->id,
                'sucursal_id' => backpack_user()->sucursal_id,
                'apertura_id' => $apertura->id
            ]);
            $apertura->estado = 'cerrado';
            $apertura->save();
            DB::commit();
            return response()->json([
                'message' => 'Corte realizado con exito',
                'corte' => $corte
            ], 200);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()
                ->json([
                    'error' => $e->getMessage()
                ], 500);
        }
    }

}
