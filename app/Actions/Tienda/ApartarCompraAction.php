<?php

namespace App\Actions\Tienda;

use App\Models\CompraPendiente;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Scopes\SucursalFilterScope;
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
    /**
     * Tiempo que el cliente tiene para completar el pago antes de perder el horario.
     *
     * La sesión de Stripe vence junto con el apartado, y Stripe exige que una
     * sesión de Checkout dure al menos 30 minutos: con 20 rechazaba todas. El
     * minuto extra cubre el tiempo entre apartar y crear la sesión. Al cliente se
     * le dicen 30.
     */
    public const MINUTOS_PARA_PAGAR = 31;

    public function ejecutar(array $datos): CompraPendiente
    {
        $sucursal = Sucursal::query()->find($datos['sucursal_id']);

        if (! $sucursal) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no existe.']);
        }

        $producto = Producto::query()
            ->withoutGlobalScope(SucursalFilterScope::class)
            ->where('id', $datos['producto_id'])
            ->where('sucursal_id', $sucursal->id)
            ->vendibleEnLinea()
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

        $tramos = empty($datos['horas'])
            ? $this->tramosDelHorario($producto, $sucursal, $datos['fecha'], $datos['hora'], $personas)
            : $this->tramosElegidos($producto, $sucursal, $datos['fecha'], $datos['horas'], $personas);

        // El apartado y la comprobación van en la misma transacción para que dos
        // clientes que eligen el mismo horario a la vez no se aparten los dos.
        return DB::transaction(function () use ($producto, $sucursal, $personas, $tramos, $datos, $descuento) {
            if (! Disponibilidad::tramosConCupo($sucursal, $tramos, $personas)) {
                throw ValidationException::withMessages([
                    'hora' => 'Ese horario se acaba de llenar. Elige otro, por favor.',
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
                'clave_idempotencia' => $datos['clave_idempotencia'] ?? null,
                'expira_en' => Carbon::now()->addMinutes(self::MINUTOS_PARA_PAGAR),
            ]);
        });
    }

    /**
     * Toma los tramos del horario elegido de la propia disponibilidad, en vez de
     * reconstruirlos con lo que mandó el navegador. Así un horario inventado o ya
     * pasado no puede colarse.
     */
    private function tramosDelHorario(Producto $producto, Sucursal $sucursal, string $fecha, string $hora, int $personas): array
    {
        $dia = Carbon::parse($fecha);
        $opciones = Disponibilidad::paraProducto($producto, $sucursal, $dia, null, $personas);

        $elegida = collect($opciones)->firstWhere('inicio', $hora);

        if (! $elegida) {
            throw ValidationException::withMessages([
                'hora' => 'Ese horario ya no está disponible. Elige otro, por favor.',
            ]);
        }

        return $this->limpiarTramos($elegida['tramos']);
    }

    /**
     * Una hora por recorrido, elegida por el cliente. Igual que arriba, cada hora
     * se busca en la disponibilidad del propio recorrido en vez de confiar en lo
     * que mandó el navegador: así respeta cupo, anticipación y cierre de ese
     * recorrido. Además el grupo no puede estar en dos recorridos a la vez, la
     * misma regla que aplica el punto de venta.
     *
     * Los tramos se guardan en orden de hora, no en el del paquete: el primero es
     * el que fecha la compra en Stripe y en la venta.
     */
    private function tramosElegidos(Producto $producto, Sucursal $sucursal, string $fecha, array $horas, int $personas): array
    {
        $dia = Carbon::parse($fecha);
        $recorridos = Disponibilidad::recorridosDe($producto)->toBase()->values();

        if (count($horas) !== $recorridos->count()) {
            throw ValidationException::withMessages([
                'horas' => 'Elige un horario para cada recorrido.',
            ]);
        }

        $tramos = $recorridos->map(function (Producto $recorrido, int $i) use ($sucursal, $dia, $horas, $personas) {
            $elegida = collect(Disponibilidad::paraProducto($recorrido, $sucursal, $dia, null, $personas))
                ->firstWhere('inicio', $horas[$i]);

            if (! $elegida) {
                throw ValidationException::withMessages([
                    'horas' => "El horario de {$recorrido->descripcion} ya no está disponible. Elige otro, por favor.",
                ]);
            }

            return $elegida['tramos'][0];
        });

        $intervalo = Disponibilidad::intervaloDe($sucursal, $dia);

        foreach ($tramos as $i => $tramo) {
            foreach ($tramos->slice($i + 1) as $otro) {
                $separacion = abs(Carbon::parse($tramo['inicio'])->diffInMinutes(Carbon::parse($otro['inicio']), false));

                if ($separacion < $intervalo) {
                    throw ValidationException::withMessages([
                        'horas' => "{$tramo['producto']} y {$otro['producto']} quedan a la misma hora. Deja al menos {$intervalo} minutos entre recorridos.",
                    ]);
                }
            }
        }

        return $this->limpiarTramos($tramos->sortBy('inicio')->values()->all());
    }

    /**
     * Solo lo que identifica al tramo: la ocupación es una foto de este momento y
     * no debe quedar guardada en el apartado.
     */
    private function limpiarTramos(array $tramos): array
    {
        return collect($tramos)
            ->map(fn ($tramo) => collect($tramo)->only(['producto_id', 'producto', 'inicio'])->all())
            ->values()
            ->all();
    }
}
