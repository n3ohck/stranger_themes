<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EgresoRequest extends FormRequest
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
            'monto' => 'required|numeric',
            'descripcion' => 'required|string',
            'tipo_pago' => 'required|string',
            'estatus' => 'required|string',
            'referencia' => 'nullable|string',
            'imagen' => 'nullable|file',
            'fecha_pago' => 'required|date',
            'sucursal_id' => 'required|exists:sucursales,id',
            'user_id' => 'required|exists:users,id',
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
            'monto.required' => 'El monto es requerido',
            'monto.numeric' => 'El monto debe ser un número',
            'descripcion.required' => 'La descripción es requerida',
            'descripcion.string' => 'La descripción debe ser un texto',
            'tipo_pago.required' => 'El tipo de pago es requerido',
            'tipo_pago.string' => 'El tipo de pago debe ser un texto',
            'estatus.required' => 'El estatus es requerido',
            'estatus.string' => 'El estatus debe ser un texto',
            'referencia.string' => 'La referencia debe ser un texto',
            'imagen.image' => 'La imagen debe ser un archivo de imagen',
        ];
    }
}
