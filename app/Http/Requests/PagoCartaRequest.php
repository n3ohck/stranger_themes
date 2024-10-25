<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagoCartaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'importe' => 'required|numeric',
            'fecha_documento' => 'required|date',
            'fecha_pago' => 'required|date',
            'pago_concepto_id' => 'required|exists:pago_conceptos,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'contenido_adicional' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'hash' => 'nullable|string',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'importe.required' => 'El importe es requerido',
            'importe.numeric' => 'El importe debe ser un número',
            'fecha_documento.required' => 'La fecha de documento es requerida',
            'fecha_documento.date' => 'La fecha de documento debe ser una fecha',
            'fecha_pago.required' => 'La fecha de pago es requerida',
            'fecha_pago.date' => 'La fecha de pago debe ser una fecha',
            'pago_concepto_id.required' => 'El concepto de pago es requerido',
            'pago_concepto_id.exists' => 'El concepto de pago no existe',
            'sucursal_id.required' => 'La sucursal es requerida',
            'sucursal_id.exists' => 'La sucursal no existe',
            'contenido_adicional.string' => 'El contenido adicional debe ser una cadena de texto',
            'user_id.exists' => 'El empleado no existe',
            'user_id.required' => 'El empleado es requerido',
        ];
    }
}
