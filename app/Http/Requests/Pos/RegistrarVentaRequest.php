<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarVentaRequest extends FormRequest
{
    public function authorize()
    {
        return true; // El acceso ya lo resuelven jwt.verify y pos.user.
    }

    public function rules()
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'min:1'],
            'items.*.codigo_descuento' => ['nullable', 'string', 'max:100'],
            'items.*.cliente' => ['nullable', 'string', 'max:255'],

            'items.*.reservaciones' => ['nullable', 'array'],
            'items.*.reservaciones.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'items.*.reservaciones.*.nombre' => ['nullable', 'string', 'max:255'],
            'items.*.reservaciones.*.personas' => ['nullable', 'numeric', 'min:1'],
            'items.*.reservaciones.*.fecha' => ['required_with:items.*.reservaciones', 'date_format:Y-m-d'],
            'items.*.reservaciones.*.hora' => ['required_with:items.*.reservaciones', 'date_format:H:i'],

            // Sin 'min:1': una venta cubierta al 100% por un código de cortesía no
            // lleva ningún pago. Que el importe recibido alcance lo valida
            // RegistrarVentaAction, que es quien conoce el total real.
            'pagos' => ['present', 'array'],
            'pagos.*.tipo' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:255'],

            'cliente.nombre' => ['nullable', 'string', 'max:255'],
            'cliente.telefono' => ['nullable', 'string', 'max:50'],
            'cliente.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'items.required' => 'No hay productos en la venta.',
            'pagos.present' => 'Falta la sección de pagos en la venta.',
            'pagos.*.tipo.in' => 'Forma de pago no válida para el punto de venta.',
            'items.*.reservaciones.*.fecha.date_format' => 'La fecha de la reservación debe tener formato AAAA-MM-DD.',
            'items.*.reservaciones.*.hora.date_format' => 'La hora de la reservación debe tener formato HH:MM.',
        ];
    }
}
