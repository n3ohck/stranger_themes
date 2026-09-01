<?php

namespace App\Actions\Tienda;

use App\Models\CompraPendiente;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Support\Tienda\DescuentoAplicado;
use App\Support\Tienda\Disponibilidad;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aparta los horarios elegidos y deja la compra lista para cobrar.
 *
 * El precio se calcula aquí a partir del catálogo. El navegador solo dice qué
 * producto, para cuántas personas y a qué hora; nunca manda importes. Es la misma
 * regla que sigue el punto de venta, y la razón es la misma: lo que viaja por el
 * navegador lo puede modificar cualquiera con la consola abierta.
 */
class ApartarCompraAction
{
    /** Tiempo que el cliente tiene para completar el pago antes de perder el horario. */
    public const MINUTOS_PARA_PAGAR = 20;

    public function ejecutar(array $datos): CompraPendiente
    {
        $sucursal = Sucursal::query()->find($datos['sucursal_id']);

        if (! $sucursal) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no existe.']);
        }

        $producto = Producto::query()
            ->withoutGlobalScopes()
            ->where('id', $datos['producto_id'])
            ->where('sucursal_id', $sucursal->id)
            ->where('visible_en_tienda', true)
            ->first();

        if (! $producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'Ese recorrido no está disponible para compra en línea en esa sucursal.',
            ]);
        }

        $personas = (int) $datos['personas'];

        if ($producto->capacidad && $personas > $producto->capacidad) {
            throw ValidationException::withMessages([
                'personas' => "{$producto->descripcion} admite hasta {$producto->capacidad} participantes por horario.",
            ]);
        }

        // El descuento se resuelve aquí, no se recibe calculado: el navegador solo
        // manda el código. Si no aplica, revienta antes de apartar nada.
        $descuento = DescuentoAplicado::resolver($datos['codigo_descuento'] ?? null, $producto, $personas);

        $tramos = $this->tramosDelHorario($producto, $sucursal, $datos['fecha'], $datos['hora']);

        // El apartado y la comprobación van en la misma transacción para que dos
        // clientes que eligen el mismo horario a la vez no se aparten los dos.
        return DB::transaction(function () use ($producto, $sucursal, $personas, $tramos, $datos, $descuento) {
            if (! Disponibilidad::tramosSiguenLibres($sucursal, $tramos)) {
                throw ValidationException::withMessages([
                    'hora' => 'Ese horario acaba de ocuparse. Elige otro, por favor.',
                ]);
            }

            return CompraPendiente::create([
                'referencia' => (string) Str::uuid(),
                'sucursal_id' => $sucursal->id,
                'producto_id' => $producto->id,
                'personas' => $personas,
                'horarios' => $tramos,
                'nombre' => $datos['nombre'],
                'email' => $datos['email'],
                'telefono' => $datos['telefono'] ?? null,
                'total' => $descuento->total(),
                'descuento' => $descuento->descuentoTotal(),
                'codigo_descuento' => $descuento->codigo,
                'descuento_id' => $descuento->id,
                'porcentaje_descuento' => $descuento->porcentaje,
                'estado' => 'apartada',
                'expira_en' => Carbon::now()->addMinutes(self::MINUTOS_PARA_PAGAR),
            ]);
        });
    }

    /**
     * Toma los tramos del horario elegido de la propia disponibilidad, en vez de
     * reconstruirlos con lo que mandó el navegador. Así un horario inventado o ya
     * pasado no puede colarse.
     */
    private function tramosDelHorario(Producto $producto, Sucursal $sucursal, string $fecha, string $hora): array
    {
        $dia = Carbon::parse($fecha);
        $opciones = Disponibilidad::paraProducto($producto, $sucursal, $dia);

        $elegida = collect($opciones)->firstWhere('inicio', $hora);

        if (! $elegida) {
            throw ValidationException::withMessages([
                'hora' => 'Ese horario ya no está disponible. Elige otro, por favor.',
            ]);
        }

        return $elegida['tramos'];
    }
}
