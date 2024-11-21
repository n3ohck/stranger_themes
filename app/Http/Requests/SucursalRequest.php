<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SucursalRequest extends FormRequest
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
            'razon_social' => 'required|min:5|max:255',
            'rfc' => 'required|min:10|max:13',
            'email'=> 'required|email:rfc,dns',
            'telefono' => 'nullable|max:10',
            'direccion' => 'nullable',
            'logotipo' => 'nullable',
            'horarios' => 'required|json'
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
            'razon_social.required' => 'La razon social o nombre de la sucursal es requerido.',
            'razon_social.max' => 'La razon social debe contener maximo 255 caracteres.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.min' => 'EL RFC debe contener minimo 10 caracteres.',
            'rfc.max' => 'El RFC debe contener maximo 13 caracteres.',
            'email.required'=> 'El campo de correo electronico es obligatorio.',
            'email.email'=> 'El campo de correo electronico es invalido.',
            'telefono.max' => 'El campo de telefono debe contener maximo 10 caracteres.',
            'horarios.required' => 'Los horarios son obligatorios.'
        ];
    }
}
