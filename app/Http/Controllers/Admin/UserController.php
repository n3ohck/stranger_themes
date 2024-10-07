<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sucursal;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use App\Http\Requests\UserRequest as StoreRequest;
use App\Http\Requests\UserRequest as UpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use JWTAuth;
use Matrix\Exception;


class UserController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {
        store as traitStore;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        update as traitUpdate;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        $this->crud->setModel(config('backpack.permissionmanager.models.user'));
        $this->crud->setEntityNameStrings(trans('backpack::permissionmanager.user'), trans('backpack::permissionmanager.users'));
        $this->crud->setRoute(backpack_url('user'));
    }

    public function setupListOperation()
    {
        $this->crud->addColumns([
            [
                'name'  => 'first_name',
                'label' => 'Nombre(s)',
                'type'  => 'text',
            ],
            [
                'name'  => 'last_name',
                'label' => 'Apellido(s)',
                'type'  => 'text',
            ],
            [
                'name'  => 'user',
                'label' => 'Nombre de usuario',
                'type'  => 'text',
            ],
            [
                // 1-n relationship
                'label'     => 'Sucursal', // Table column heading
                'type'      => 'select',
                'name'      => 'sucursal_id', // the column that contains the ID of that connected entity;
                'entity'    => 'sucursal', // the method that defines the relationship in your Model
                'attribute' => 'razon_social', // foreign key attribute that is shown to user
                'model'     => Sucursal::class, // foreign key model
            ],
            [
                'name'  => 'es_vendedor',
                'label' => 'Es vendedor',
                'type'  => 'text',
            ],
            [
                'name'  => 'email',
                'label' => trans('backpack::permissionmanager.email'),
                'type'  => 'email',
            ],
            [ // n-n relationship (with pivot table)
                'label'     => trans('backpack::permissionmanager.roles'), // Table column heading
                'type'      => 'select_multiple',
                'name'      => 'roles', // the method that defines the relationship in your Model
                'entity'    => 'roles', // the method that defines the relationship in your Model
                'attribute' => 'name', // foreign key attribute that is shown to user
                'model'     => config('permission.models.role'), // foreign key model
            ],
            [ // n-n relationship (with pivot table)
                'label'     => trans('backpack::permissionmanager.extra_permissions'), // Table column heading
                'type'      => 'select_multiple',
                'name'      => 'permissions', // the method that defines the relationship in your Model
                'entity'    => 'permissions', // the method that defines the relationship in your Model
                'attribute' => 'name', // foreign key attribute that is shown to user
                'model'     => config('permission.models.permission'), // foreign key model
            ],
        ]);

        // Role Filter
        $this->crud->addFilter(
            [
                'name'  => 'role',
                'type'  => 'dropdown',
                'label' => trans('backpack::permissionmanager.role'),
            ],
            config('permission.models.role')::all()->pluck('name', 'id')->toArray(),
            function ($value) { // if the filter is active
                $this->crud->addClause('whereHas', 'roles', function ($query) use ($value) {
                    $query->where('role_id', '=', $value);
                });
            }
        );

        // Extra Permission Filter
        $this->crud->addFilter(
            [
                'name'  => 'permissions',
                'type'  => 'select2',
                'label' => trans('backpack::permissionmanager.extra_permissions'),
            ],
            config('permission.models.permission')::all()->pluck('name', 'id')->toArray(),
            function ($value) { // if the filter is active
                $this->crud->addClause('whereHas', 'permissions', function ($query) use ($value) {
                    $query->where('permission_id', '=', $value);
                });
            }
        );

        //Es vendedor
        $this->crud->addFilter(
            [
                'name'  => 'es_vendedor',
                'type'  => 'dropdown',
                'label' => 'Es vendedor',
            ],
            ['si' => 'Si', 'no' => 'No'],
            function ($value) { // if the filter is active
                $this->crud->addClause('where', 'es_vendedor', '=', $value);
            }
        );

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

    public function setupCreateOperation()
    {
        $this->addUserFields();
        $this->crud->setValidation(StoreRequest::class);
    }

    public function setupUpdateOperation()
    {
        $this->addUserFields();
        $this->crud->setValidation(UpdateRequest::class);
    }

    /**
     * Store a newly created resource in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->setRequest($this->crud->validateRequest());
        $this->crud->setRequest($this->handlePasswordInput($this->crud->getRequest()));
        $this->crud->unsetValidation(); // validation has already been run

        return $this->traitStore();
    }

    /**
     * Update the specified resource in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        $this->crud->setRequest($this->crud->validateRequest());
        $this->crud->setRequest($this->handlePasswordInput($this->crud->getRequest()));
        $this->crud->unsetValidation(); // validation has already been run

        return $this->traitUpdate();
    }

    /**
     * Handle password input fields.
     */
    protected function handlePasswordInput($request)
    {
        // Remove fields not present on the user.
        $request->request->remove('password_confirmation');
        $request->request->remove('roles_show');
        $request->request->remove('permissions_show');

        // Encrypt password if specified.
        if ($request->input('password')) {
            $request->request->set('password', Hash::make($request->input('password')));
        } else {
            $request->request->remove('password');
        }

        return $request;
    }

    protected function addUserFields()
    {
        $this->crud->addFields([
            [
                'name'  => 'first_name',
                'label' => 'Nombre(s)',
                'type'  => 'text',
            ],
            [
                'name'  => 'last_name',
                'label' => 'Apellido(s)',
                'type'  => 'text',
            ],
            [
                'name'  => 'user',
                'label' => 'Nombre de usuario',
                'type'  => 'text',
            ],
            [
                'name'  => 'email',
                'label' => trans('backpack::permissionmanager.email'),
                'type'  => 'email',
            ],
            [
                'name'  => 'password',
                'label' => trans('backpack::permissionmanager.password'),
                'type'  => 'password',
            ],
            [
                'name'  => 'password_confirmation',
                'label' => trans('backpack::permissionmanager.password_confirmation'),
                'type'  => 'password',
            ],
            [
                'name'  => 'password_confirmation',
                'label' => trans('backpack::permissionmanager.password_confirmation'),
                'type'  => 'password',
            ],
            [
                'name'  => 'es_vendedor',
                'label' => 'Es vendedor',
                'type'  => 'enum',
            ],
            [   // 1-n relationship
                'label'       => "Sucursal", // Table column heading
                'type'        => "select2_from_ajax",
                'name'        => 'sucursal_id', // the column that contains the ID of that connected entity
                'entity'      => 'sucursal', // the method that defines the relationship in your Model
                'attribute'   => "razon_social", // foreign key attribute that is shown to user
                'data_source' => url("webapi/sucursal"), // url to controller search function (with /{id} should return model)
                'placeholder'             => "Seleccione sucursal", // placeholder for the select
                'minimum_input_length'    => 0, // minimum characters to type before querying results
                'model'                   => Sucursal::class, // foreign key model
                'method'                  => 'GET', // optional - HTTP method to use for the AJAX call (GET, POST)
            ],
            [
                // two interconnected entities
                'label'             => trans('backpack::permissionmanager.user_role_permission'),
                'field_unique_name' => 'user_role_permission',
                'type'              => 'checklist_dependency',
                'name'              => ['roles', 'permissions'],
                'subfields'         => [
                    'primary' => [
                        'label'            => trans('backpack::permissionmanager.roles'),
                        'name'             => 'roles', // the method that defines the relationship in your Model
                        'entity'           => 'roles', // the method that defines the relationship in your Model
                        'entity_secondary' => 'permissions', // the method that defines the relationship in your Model
                        'attribute'        => 'name', // foreign key attribute that is shown to user
                        'model'            => config('permission.models.role'), // foreign key model
                        'pivot'            => true, // on create&update, do you need to add/delete pivot table entries?]
                        'number_columns'   => 3, //can be 1,2,3,4,6
                    ],
                    'secondary' => [
                        'label'          => ucfirst(trans('backpack::permissionmanager.permission_singular')),
                        'name'           => 'permissions', // the method that defines the relationship in your Model
                        'entity'         => 'permissions', // the method that defines the relationship in your Model
                        'entity_primary' => 'roles', // the method that defines the relationship in your Model
                        'attribute'      => 'name', // foreign key attribute that is shown to user
                        'model'          => config('permission.models.permission'), // foreign key model
                        'pivot'          => true, // on create&update, do you need to add/delete pivot table entries?]
                        'number_columns' => 3, //can be 1,2,3,4,6
                    ],
                ],
            ],
        ]);
    }

    public function getAuthenticatedUser()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['token_expired'], 403);
        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['token_invalid'], $e->getStatusCode());
        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['token_absent'], $e->getStatusCode());
        }
        return response()->json(compact('user'));
    }

    public function changeProfileImage(Request $request){
        try{
            $user = User::whereId( $request->get('user_id') )->first();
            if( !isset( $user->id ) ) throw new Exception('Usuario no existe', 404);
            $user->profile_image = $request->get('image');
            $user->save();
            return response()->json(['message' => 'Se actualizo con exito tu foto de perfil '], 200);
        }catch (Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
