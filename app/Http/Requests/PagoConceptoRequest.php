<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagoConceptoRequest extends FormRequest
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
            'descripcion' => 'required',
            'sucursal_id' => 'required|exists:sucursales,id',
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
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'sucursal_id.required' => 'La sucursal es requerida.',
            'descripcion.required' => 'La descripción es requerida.',
        ];
    }
}
