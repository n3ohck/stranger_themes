<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SucursalRequest;
use App\Models\Sucursal;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class SucursalCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SucursalCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Sucursal::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/sucursal');
        CRUD::setEntityNameStrings('Sucursal', 'Sucursales');
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
                'name'      => 'logotipo', // The db column name
                'label'     => 'Logotipo', // Table column heading
                'type'      => 'image',
            ],
            [
                'name'  => 'razon_social',
                'label' => 'Razon social',
                'type'  => 'text'
            ],
            [
                'name'  => 'email',
                'label' => 'Email',
                'type'  => 'text'
            ],
            [
                'name' => 'hora_apertura',
                'label' => 'Hora Apertura',
                'type' => 'time'
            ],
            [
                'name' => 'hora_cierre',
                'label' => 'Hora Cierre',
                'type' => 'time'
            ],
            [
                'name'  => 'created_at',
                'label' => 'Fecha Registro',
                'type'  => 'text'
            ],
        ]);
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
        CRUD::setValidation(SucursalRequest::class);
        $this->crud->addFields([
            [   // Text
                'name'  => 'razon_social',
                'label' => "Razon social",
                'type'  => 'text',
                'hint'       => 'Nombre de la sucursal.', // helpful text, show up after input
                'placeholder' => 'Nombre de la sucursal',
            ],
            [   // Text
                'name'  => 'rfc',
                'label' => "RFC",
                'type'  => 'text',
                'hint'       => 'Registro federal de causantes.', // helpful text, show up after input
                'placeholder' => 'RFC de la sucursal',
            ],
            [   // Text
                'name'  => 'email',
                'label' => "Email",
                'type'  => 'text',
                'hint'       => 'Correo electronico.', // helpful text, show up after input
                'placeholder' => 'Correo electronico'
            ],
            [   // Text
                'name'  => 'telefono',
                'label' => "Telefono",
                'type'  => 'text',
                'hint'       => 'Telefono.', // helpful text, show up after input
                'placeholder' => 'Telefono'
            ],
            [   // Text
                'name'  => 'direccion',
                'label' => "Direccion",
                'type'  => 'textarea',
                'hint'       => 'Direccion completa.', // helpful text, show up after input
                'placeholder' => 'Direccion completa.',
            ],
            [   // Time
                'name'  => 'hora_apertura',
                'label' => "Hora Apertura",
                'type'  => 'time',
                'hint'       => 'Hora de apertura.', // helpful text, show up after input
                'placeholder' => 'Hora de apertura',
            ],
            [   // Time
                'name'  => 'hora_cierre',
                'label' => "Hora Cierre",
                'type'  => 'time',
                'hint'       => 'Hora de cierre.', // helpful text, show up after input
                'placeholder' => 'Hora de cierre',
            ],
            [
                'label' => "Logotipo",
                'name' => "logotipo",
                'type' => 'image',
                'crop' => true,
            ],
        ]);

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
        $search_term = $request->input('q');
        return Sucursal::query()
            ->select(['id','razon_social'])
            ->when(isset($search_term), function ($q) use ($search_term) {
                $q->where('razon_social', 'LIKE', '%' . $search_term . '%');
            })
            ->orderBy('razon_social', 'DESC')
            ->paginate(10);
    }

    public function get(Request $request)
    {
        try {
            $user = $request->get('user_id');
            if( !$user ) throw new \Exception('No se ha proporcionado un usuario.');
            $user = User::find($user);
            if( !$user ) throw new \Exception('No se ha encontrado el usuario.');
            $sucursal = Sucursal::query()->where('id', $user->sucursal_id)->first();
            if( !$sucursal ) throw new \Exception('No se ha encontrado la sucursal.');
            return response()->json([
                'message' => 'Consuta exitosa.',
                'sucursal' => $sucursal
            ],200);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
