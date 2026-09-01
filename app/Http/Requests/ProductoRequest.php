<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductoRequest extends FormRequest
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
            'codigo' => 'required',
            'descripcion' => 'required',
            'precio' => 'required|numeric',
            'existencia' => 'required|integer',
            'tipo' => 'required',
            'sucursal_id' => 'required|exists:sucursales,id',
            // Sin capacidad ni duración la tienda no puede calcular horarios, así que
            // se exigen justo cuando el producto se pone a la venta en línea.
            'visible_en_tienda' => 'nullable|boolean',
            'capacidad' => 'nullable|integer|min:1|max:100|required_if:visible_en_tienda,1',
            'duracion_minutos' => 'nullable|integer|min:5|max:600|required_if:visible_en_tienda,1',
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
            'capacidad.required_if' => 'Para vender en línea hay que indicar cuántas personas caben por horario.',
            'duracion_minutos.required_if' => 'Para vender en línea hay que indicar cuánto dura el recorrido.',
            'codigo.required' => 'El campo código es obligatorio.',
            'descripcion.required' => 'El campo descripción es obligatorio.',
            'precio.required' => 'El campo precio es obligatorio.',
            'precio.numeric' => 'El campo precio debe ser numérico.',
            'existencia.required' => 'El campo existencia es obligatorio.',
            'existencia.integer' => 'El campo existencia debe ser un número entero.',
            'tipo.required' => 'El campo tipo es obligatorio.',
            'sucursal_id.required' => 'El campo sucursal es obligatorio.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }
}
