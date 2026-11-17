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
            'precios_por_personas' => ['nullable', function ($atributo, $valor, $falla) {
                if ($error = self::errorEnRangos($valor)) {
                    $falla($error);
                }
            }],
        ];
    }

    /**
     * Revisa los rangos de precio por personas. Llegan del repeatable de Backpack
     * como texto JSON; las filas vacías se ignoran (el modelo las descarta).
     *
     * Los rangos no pueden encimarse: un grupo de 4 con "1 a 4" y "4 a 6" no
     * tendría un precio claro.
     */
    public static function errorEnRangos($valor): ?string
    {
        $filas = is_string($valor) ? json_decode($valor, true) : $valor;

        if ($valor !== null && $valor !== '' && ! is_array($filas)) {
            return 'Los rangos de precio no tienen un formato válido.';
        }

        $rangos = [];

        foreach ($filas ?? [] as $i => $fila) {
            $n = $i + 1;
            $desde = $fila['desde'] ?? '';
            $hasta = $fila['hasta'] ?? '';
            $precio = $fila['precio'] ?? '';

            if ($desde === '' && $hasta === '' && $precio === '') {
                continue;
            }

            if (! ctype_digit((string) $desde) || (int) $desde < 1) {
                return "Rango {$n}: \"Desde\" debe ser un número entero de personas, mínimo 1.";
            }

            if ($hasta !== '' && $hasta !== null && (! ctype_digit((string) $hasta) || (int) $hasta < (int) $desde)) {
                return "Rango {$n}: \"Hasta\" debe ser un número entero mayor o igual a \"Desde\", o quedar vacío.";
            }

            if (! is_numeric($precio) || (float) $precio < 0) {
                return "Rango {$n}: indica el precio por persona.";
            }

            $rangos[] = [
                'desde' => (int) $desde,
                'hasta' => $hasta === '' || $hasta === null ? PHP_INT_MAX : (int) $hasta,
            ];
        }

        usort($rangos, fn ($a, $b) => $a['desde'] <=> $b['desde']);

        for ($i = 1; $i < count($rangos); $i++) {
            if ($rangos[$i]['desde'] <= $rangos[$i - 1]['hasta']) {
                return 'Los rangos de personas se enciman: cada número de personas debe caer en un solo rango.';
            }
        }

        return null;
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
