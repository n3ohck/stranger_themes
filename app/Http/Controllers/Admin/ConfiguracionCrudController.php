<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ConfiguracionRequest;
use App\Models\Sucursal;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ConfiguracionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ConfiguracionCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Configuracion::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/configuracion');
        CRUD::setEntityNameStrings('configuracion', 'configuraciones');
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
                'name' => 'sucursal_id',
                'label' => 'Sucursal',
                'type' => 'select',
                'entity' => 'sucursal',
                'attribute' => 'razon_social',
                'model' => Sucursal::class
            ],
            [
                'name' => 'contenido_carta_pago',
                'label' => 'Contenido de la carta de pago',
                'type' => 'text',
            ],
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
        CRUD::setValidation(ConfiguracionRequest::class);

        $this->crud->addFields([
            [
                'name' => 'sucursal_id',
                'label' => 'Sucursal',
                'type' => 'select',
                'entity' => 'sucursal',
                'attribute' => 'razon_social',
                'model' => Sucursal::class
            ],
            [   // WYSIWYG Editor
                'name'  => 'contenido_carta_pago',
                'label' => 'Contenido de la carta de pago',
                'type'  => 'wysiwyg',
                'placeholder' => 'Contenido de la carta de pago',
                'hint'  => 'Puedes colocar los siguientes tags en el texto para que sean automaticamente reemplazados en la carta. {nombre_empleado},{apellido_empleado},{concepto_pago},{importe_pago} y {fecha_documento}',
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
}
