<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DescuentoRequest extends FormRequest
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
            'codigo' => 'required|unique:descuentos,codigo,'.$this->route('id').',id,deleted_at,NULL',
            'porcentaje' => 'required|numeric',
            'sucursal_id' => 'required|exists:sucursales,id',
            'estatus' => 'required|in:activo,inactivo',
            'producto_tipo' => 'required'
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
            'codigo.required' => 'El código es requerido',
            'codigo.unique' => 'El código ya existe',
            'porcentaje.required' => 'El porcentaje es requerido',
            'porcentaje.numeric' => 'El porcentaje debe ser numérico',
            'sucursal_id.required' => 'La sucursal es requerida',
            'sucursal_id.exists' => 'La sucursal no existe',
            'estatus.required' => 'El estatus es requerido',
            'estatus.in' => 'El estatus debe ser activo o inactivo',
            'producto_tipo.required' => 'El tipo de producto es requerido'
        ];
    }
}
