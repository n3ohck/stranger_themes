<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmpleadoRequest extends FormRequest
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
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombres' => 'required|min:3|max:255',
            'apellidos' => 'required|min:3|max:255',
            'email' => 'required|email',
            'telefono' => 'nullable|min:10|max:10',
            'salario' => 'required|numeric|min:1'
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
            'sucursal_id.required' => 'El campo sucursal es obligatorio.',
            'sucursal_id.exists' => 'La sucursal seleccionada no es válida.',
            'nombres.required' => 'El campo nombres es obligatorio.',
            'nombres.min' => 'El campo nombres debe tener al menos 3 caracteres.',
            'nombres.max' => 'El campo nombres debe tener máximo 255 caracteres.',
            'apellidos.required' => 'El campo apellidos es obligatorio.',
            'apellidos.min' => 'El campo apellidos debe tener al menos 3 caracteres.',
            'apellidos.max' => 'El campo apellidos debe tener máximo 255 caracteres.',
            'email.required' => 'El campo email es obligatorio.',
            'email.email' => 'El campo email debe ser un correo electrónico válido.',
            'telefono.min' => 'El campo teléfono debe tener al menos 10 caracteres.',
            'telefono.max' => 'El campo teléfono debe tener máximo 10 caracteres.',
            'salario.required' => 'El campo salario es obligatorio.',
        ];
    }
}
