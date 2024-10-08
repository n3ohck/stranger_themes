<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PagoCartaRequest;
use App\Models\Configuracion;
use App\Models\PagoCarta;
use App\Models\PagoConcepto;
use App\Models\Sucursal;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\DB;
use Prologue\Alerts\Facades\Alert;

/**
 * Class PagoCartaCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PagoCartaCrudController extends CrudController
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
        CRUD::setModel(\App\Models\PagoCarta::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/pago-carta');
        CRUD::setEntityNameStrings('pago carta', 'pago cartas');
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
                // 1-n relationship
                'label' => 'Empleado', // Table column heading
                'type' => 'select',
                'name' => 'user_id', // the column that contains the ID of that connected entity;
                'entity' => 'user', // the method that defines the relationship in your Model
                'attribute' => 'nombre_completo', // foreign key attribute that is shown to user
                'model' => User::class, // foreign key model
            ],
            [
                'name' => 'fecha_documento',
                'type' => 'date',
                'label' => 'Fecha Documento',
            ],
            [
                'name' => 'fecha_pago',
                'type' => 'date',
                'label' => 'Fecha Pago',
            ],
            [
                // 1-n relationship
                'label' => 'Concepto', // Table column heading
                'type' => 'select',
                'name' => 'pago_concepto_id', // the column that contains the ID of that connected entity;
                'entity' => 'pagoConcepto', // the method that defines the relationship in your Model
                'attribute' => 'descripcion', // foreign key attribute that is shown to user
                'model' => PagoConcepto::class, // foreign key model
            ],
            [
                'name' => 'importe',
                'type' => 'number',
                'label' => 'Importe',
            ],
            [
                'name' => 'sucursal_id',
                'type' => 'relationship',
                'label' => 'Sucursal',
                'attribute' => 'razon_social',
            ],
            [
                'name' => 'created_at',
                'type' => 'date',
                'label' => 'Creado el',
            ]
        ]);

        $this->crud->addFilter([ // dropdown filter
            'name' => 'sucursal_id',
            'type' => 'dropdown',
            'label' => 'Sucursal'
        ], Sucursal::query()
            ->select('id', 'razon_social')
            ->get()
            ->pluck('razon_social', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'sucursal_id', $value);
        });

        $this->crud->addFilter([ // dropdown filter
            'name' => 'pago_concepto_id',
            'type' => 'dropdown',
            'label' => 'Concepto'
        ], PagoConcepto::query()
            ->select('id', 'descripcion')
            ->get()
            ->pluck('descripcion', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'concepto_id', $value);
        });

        $this->crud->addFilter([ // dropdown filter
            'name' => 'user_id',
            'type' => 'dropdown',
            'label' => 'Usuario'
        ], User::query()
            ->select('id', DB::raw('CONCAT(first_name, " ", last_name) as full_name'))
            ->get()
            ->pluck('full_name', 'id')
            ->filter()
            ->toArray(), function ($value) { // if the filter is active
            $this->crud->addClause('where', 'user_id', $value);
        });

        $this->crud->addFilter([ // daterange filter
            'type' => 'date_range',
            'name' => 'fecha_pago',
            'label' => 'Fecha de pago'
        ],
            false,
            function ($value) { // if the filter is active, apply these constraints
                $dates = json_decode($value);
                $datesFrom = \Carbon\Carbon::parse($dates->from)->startOfDay();
                $datesTo = \Carbon\Carbon::parse($dates->to)->endOfDay();
                $this->crud->addClause('where', 'fecha_pago', '>=', $datesFrom);
                $this->crud->addClause('where', 'fecha_pago', '<=', $datesTo);
            });

        $this->crud->addFilter([ // daterange filter
            'type' => 'date_range',
            'name' => 'fecha_documento',
            'label' => 'Fecha de documento'
        ],
            false,
            function ($value) { // if the filter is active, apply these constraints
                $dates = json_decode($value);
                $datesFrom = \Carbon\Carbon::parse($dates->from)->startOfDay();
                $datesTo = \Carbon\Carbon::parse($dates->to)->endOfDay();
                $this->crud->addClause('where', 'fecha_documento', '>=', $datesFrom);
                $this->crud->addClause('where', 'fecha_documento', '<=', $datesTo);
            });

        $this->crud->addFilter([ // daterange filter
            'type' => 'date_range',
            'name' => 'created_at',
            'label' => 'Fecha de registro'
        ],
            false,
            function ($value) { // if the filter is active, apply these constraints
                $dates = json_decode($value);
                $datesFrom = \Carbon\Carbon::parse($dates->from)->startOfDay();
                $datesTo = \Carbon\Carbon::parse($dates->to)->endOfDay();
                $this->crud->addClause('where', 'created_at', '>=', $datesFrom);
                $this->crud->addClause('where', 'created_at', '<=', $datesTo);
            });

        $this->crud->addButtonFromModelFunction('line', 'pdfButton', 'pdfButton', 'beginning');

    }


    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PagoCartaRequest::class);
        request()
            ->merge([
                'sucursal_id' => backpack_user()->sucursal_id,
            ]);

        $this->crud->addFields([
            [   // 1-n relationship
                'label' => "Empleado", // Table column heading
                'type' => "select2_from_ajax",
                'name' => 'user_id', // the column that contains the ID of that connected entity
                'entity' => 'user', // the method that defines the relationship in your Model
                'attribute' => "nombre_completo", // foreign key attribute that is shown to user
                'data_source' => url("webapi/users"), // url to controller search function (with /{id} should return model)
                'delay' => 0, // the minimum amount of time between ajax requests when searching in the field
                'placeholder' => "Seleccione empleado", // placeholder for the select
                'minimum_input_length' => 0, // minimum characters to type before querying results
                'model' => User::class, // foreign key model
                'method' => 'GET', // optional - HTTP method to use for the AJAX call (GET, POST)
            ],
            [   // 1-n relationship
                'label' => "Concepto de pago", // Table column heading
                'type' => "select2_from_ajax",
                'name' => 'pago_concepto_id', // the column that contains the ID of that connected entity
                'entity' => 'pagoConcepto', // the method that defines the relationship in your Model
                'attribute' => "descripcion", // foreign key attribute that is shown to user
                'data_source' => url("webapi/pagos/concepto"), // url to controller search function (with /{id} should return model)
                'delay' => 0, // the minimum amount of time between ajax requests when searching in the field
                'placeholder' => "Seleccione concepto de pago", // placeholder for the select
                'minimum_input_length' => 0, // minimum characters to type before querying results
                'model' => PagoConcepto::class, // foreign key model
                'method' => 'GET', // optional - HTTP method to use for the AJAX call (GET, POST)
            ],
            [
                'name' => 'fecha_documento',
                'type' => 'date',
                'label' => 'Fecha Documento',
                'hint' => 'Fecha en la que se emite el documento y se muestra en el PDF como fecha de emisión',
                'wrapper' => [
                    'class' => 'form-group col-md-4'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'fecha_pago',
                'type' => 'date',
                'label' => 'Fecha Pago',
                'hint' => 'Fecha en la que realmente se emitio el pago y solo de uso interno',
                'wrapper' => [
                    'class' => 'form-group col-md-4'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'importe',
                'type' => 'number',
                'label' => 'Importe',
                'hint' => 'Es el importe total del pago',
                'attributes' => ["step" => "any"], // allow decimals
                'prefix' => "$",
                'wrapper' => [
                    'class' => 'form-group col-md-4'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [
                'name' => 'contenido_adicional',
                'type' => 'wysiwyg',
                'label' => 'Contenido Adicional',
                'hint' => 'Permite incluir texto adicional en el documento',
                'wrapper' => [
                    'class' => 'form-group col-md-12'
                ], // extra HTML attributes for the field wrapper - mostly for resizing fields
            ],
            [   // Upload
                'name' => 'archivo',
                'label' => 'Documento firmado por el empleado',
                'type' => 'upload',
                'upload' => true,
                'disk' => 'pagos',
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

    public function pdf(PagoCarta $pagoCarta)
    {
        try {
            if( !$pagoCarta ) throw new \Exception('No se ha encontrado el pago');

            $configuracion = Configuracion::query()
                ->where('sucursal_id', $pagoCarta->sucursal_id)
                ->first();

            $contenido = '';
            $contenidoAdicional = '';
            $nombre_archivo = 'recibo-pago' . str_replace(' ', '_', $pagoCarta->user->nombre_completo . $pagoCarta->fecha_documento->format('d_m_Y'));
            if (!isset($configuracion)) throw new \Exception('No se ha configurado la sucursal');
            $contenido = str_replace(
                ['{fecha_documento}', '{nombre_empleado}', '{apellido_empleado}', '{importe_pago}', '{concepto_pago}'],
                [
                    $pagoCarta->fecha_documento->format('d/m/Y'),
                    $pagoCarta->user->first_name,
                    $pagoCarta->user->last_name,
                    number_format($pagoCarta->importe, 2, '.', ','),
                    $pagoCarta->pagoConcepto->descripcion
                ],
                $configuracion->contenido_carta_pago
            );
            if ($pagoCarta->contenido_adicional) {
                $contenidoAdicional = str_replace(
                    ['{fecha_documento}', '{nombre_empleado}', '{apellido_empleado}', '{importe_pago}', '{concepto_pago}'],
                    [
                        $pagoCarta->fecha_documento->format('d/m/Y'),
                        $pagoCarta->user->first_name,
                        $pagoCarta->user->last_name,
                        number_format($pagoCarta->importe, 2, '.', ','),
                        $pagoCarta->pagoConcepto->descripcion
                    ],
                    $configuracion->contenido_adicional
                );
            }
            // Renderiza la vista y genera el PDF
            $pdf = \PDF::loadView('pagos.carta_pagos.recibo_pago', [
                'contenido' => $contenido,
                'contenido_adicional' => $contenidoAdicional,
                'titulo' => $nombre_archivo,
                'pagoCarta' => $pagoCarta,
                'sucursal' => $pagoCarta->sucursal,
            ]);

            return $pdf->inline($nombre_archivo . '.pdf');
        } catch (\Exception $e) {
            Alert::error($e->getMessage())->flash();
            return redirect()->back();
        }
    }
}
