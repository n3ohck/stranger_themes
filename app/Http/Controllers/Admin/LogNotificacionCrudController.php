<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\LogNotificacionRequest;
use App\Models\LogNotificacion;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Venta;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class LogNotificacionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class LogNotificacionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\LogNotificacion::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/log-notificacion');
        CRUD::setEntityNameStrings('log notificacion', 'log notificaciones');
        $this->crud->addButtonFromModelFunction('line', 'reenviar', 'botonReenvio', 'end');
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
            ['name' => 'email', 'type' => 'text'],
            [
                'label'     => 'Venta', // Table column heading
                'type'      => 'select',
                'name'      => 'venta_id', // the column that contains the ID of that connected entity;
                'entity'    => 'venta', // the method that defines the relationship in your Model
                'attribute' => 'folio', // foreign key attribute that is shown to user
                'model'     => Venta::class, // foreign key model
            ],
            [
                'label'     => 'Producto', // Table column heading
                'type'      => 'select',
                'name'      => 'producto_id', // the column that contains the ID of that connected entity;
                'entity'    => 'producto', // the method that defines the relationship in your Model
                'attribute' => 'descripcion', // foreign key attribute that is shown to user
                'model'     => Producto::class, // foreign key model
            ],
            [
                'label'     => 'Sucursal', // Table column heading
                'type'      => 'select',
                'name'      => 'sucursal_id', // the column that contains the ID of that connected entity;
                'entity'    => 'sucursal', // the method that defines the relationship in your Model
                'attribute' => 'razon_social', // foreign key attribute that is shown to user
                'model'     => Sucursal::class, // foreign key model
            ],
            ['name' => 'motivo', 'type' => 'text'],
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(LogNotificacionRequest::class);

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

    public function resend(LogNotificacion $notificacion){
        try{
            $notificacion->venta->load([
                'sucursal',
                'reservaciones' => function ($q) {
                    $q->with('producto');
                }
            ]);
            switch ($notificacion->motivo){
                case 'comprobante':
                    \Mail::to($notificacion->email)->send(new \App\Mail\ComprobanteMail($notificacion->venta));
                    break;
                case 'disputa':
                    \Mail::to($notificacion->email)->send(new \App\Mail\DisputaMail($notificacion->venta));
                    break;
                case 'recordatorio':
                    \Mail::to($notificacion->email)->send(new \App\Mail\RecordatorioMail($notificacion->venta));
                    break;
                default:
                    \Alert::error('Motivo no valido.')->flash();
                    return redirect()->back();
            }
            \Alert::success('Notificacion reenviada.')->flash();
            return redirect()->back();
        }catch (\Exception $e){
            \Alert::error('Error al reenviar la notificacion.')->flash();
            return redirect()->back();
        }
    }

}
