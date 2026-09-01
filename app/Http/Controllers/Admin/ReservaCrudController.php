<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ReservaRequest;
use App\Models\Reserva;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class ReservaCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ReservaCrudController extends CrudController
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
        // Estos controladores sirven al panel y también a endpoints de API que se
        // consumen sin sesión. Aquí se hacía Auth::loginUsingId(1) cuando no había
        // usuario, para que las llamadas a ->can() de más abajo no reventaran: eso
        // dejaba autenticada como administrador a cualquier petición anónima, y con
        // varias sucursales además aplicaba el filtro de la sucursal 1 a consultas
        // públicas de otras sucursales. Los permisos solo se evalúan si hay usuario;
        // las rutas de API no dependen de ellos.
        CRUD::setModel(\App\Models\Reserva::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/reserva');
        CRUD::setEntityNameStrings('reserva', 'reservas');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('cantidad_personas');
        CRUD::column('created_at');
        CRUD::column('deleted_at');
        CRUD::column('estado');
        CRUD::column('fecha');
        CRUD::column('id');
        CRUD::column('nombre_cliente');
        CRUD::column('producto_id');
        CRUD::column('sucursal_id');
        CRUD::column('updated_at');

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
        CRUD::setValidation(ReservaRequest::class);

        CRUD::field('cantidad_personas');
        CRUD::field('created_at');
        CRUD::field('deleted_at');
        CRUD::field('estado');
        CRUD::field('fecha');
        CRUD::field('id');
        CRUD::field('nombre_cliente');
        CRUD::field('producto_id');
        CRUD::field('sucursal_id');
        CRUD::field('updated_at');

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

    private function makeDate($date)
    {
        return (isset($date)) ? Carbon::parse(str_replace('T', ' ', $date)) : null;
    }

    public function fetch(Request $request)
    {
        try {
            $search = (object)[
                'query' => $request->get('query'),
                'start_date' => $this->makeDate($request->get('start_date') ?? null),
                'end_date' => $this->makeDate($request->get('end_date') ?? null),
                'status' => $request->get('status'),
                'venta_id' => $request->get('venta_id'),
                'sucursal_id' => $request->get('sucursal_id'),
            ];

            $reservas = Reserva::query()
                ->with(['producto'])
                ->Search($search)
                ->orderBy('fecha', 'desc')
                ->get();

            return response()->json([
                'message' => 'Consulta realizada con exito',
                'reservas' => $reservas,
                'qty' => $reservas->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function isAvailable(Request $request)
    {
        try {
            if (!$request->has('datetime') || !$request->has('product_id')) {
                return response()->json(['error' => 'Faltan datos fecha o producto_id'], 400);
            }
            $sucursalId = backpack_user()->sucursal_id;
            $reservas = Reserva::query()
                ->where('fecha', Carbon::parse($request->datetime))
                ->where('producto_id', $request->product_id)
                ->where('sucursal_id', $sucursalId)
                ->first();
            if (!is_null($reservas)) {
                return response()->json(['message' => 'No disponible', 'available' => false], 200);
            }
            return response()->json(['message' => 'Disponible', 'available' => true], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createReserva(Request $request)
    {
        try {
            if (!$request->has('product_id')) throw new \Exception('Falta product_id');
            if (!$request->has('datetime')) throw new \Exception('Falta fecha de reserva');
            if (!$request->has('name')) throw new \Exception('Falta name (nombre cliente) de reserva');
            // Ruta protegida por JWT: el usuario siempre existe. Antes había un
            // fallback a Auth::loginUsingId(1) que, de dispararse, habría agendado
            // la reservación en la sucursal 1 sin avisar a nadie.
            if (! backpack_user()) {
                throw new \Exception('No hay una sesión válida para crear la reservación');
            }

            $sucursalId = backpack_user()->sucursal_id;
            $reserva = Reserva::create([
                'producto_id' => $request->product_id,
                'nombre_cliente' => $request->name,
                'cantidad_personas' => $request->number,
                'fecha' => Carbon::parse($request->datetime),
                'estado' => 'confirmada',
                'sucursal_id' => $sucursalId,
                "venta_id" => $request->venta_id
            ]);
            return response()->json([
                'message' => 'Reserva creada',
                'reserva' => [
                    'id' => $reserva->id,
                    'name' => $reserva->nombre_cliente,
                    'number' => $reserva->cantidad_personas,
                    'datetime' => $reserva->fecha,
                    'status' => $reserva->estado,
                    'product_id' => $reserva->producto_id,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateReservation(Request $request, Reserva $reserva)
    {
        try{
            if(!$reserva){
                throw new \Exception('Reserva no encontrado', 404);
            }

            if (!$request->input('fecha')) throw new \Exception('Falta fecha');
            if (!$request->input('hora')) throw new \Exception('Falta hora');

            DB::beginTransaction();
            $reserva->update([
                'fecha' => Carbon::parse($request->fecha . ' ' . $request->hora),
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Reserva actualizada',
                'reserva' => $reserva
            ], 200);
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
