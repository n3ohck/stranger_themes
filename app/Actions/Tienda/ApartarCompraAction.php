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
 * Son dos pasos: apartar() en cuanto el cliente elige el horario (todavía sin sus
 * datos) y completar() al pulsar Pagar, con nombre, correo y código. Desde que se
 * aparta corren MINUTOS_APARTADO, también mientras paga en Stripe: al vencer se
 * cierra la sesión de pago (PasarelaStripe) y el horario vuelve a ofrecerse.
 *
 * El precio se calcula aquí a partir del catálogo. El navegador solo dice qué
 * producto, para cuántas personas y a qué hora; nunca manda importes. Es la misma
 * regla que sigue el punto de venta, y la razón es la misma: lo que viaja por el
 * navegador lo puede modificar cualquiera con la consola abierta.
 */
class ApartarCompraAction
{
    /**
     * Tiempo desde que se elige el horario hasta que se pierde, pago incluido.
     * Decisión del negocio: con 30 minutos los horarios quedaban bloqueados de más.
     */
    public const MINUTOS_APARTADO = 5;

    /**
     * Vida de la sesión de Stripe. Stripe exige al menos 30 minutos, así que la
     * sesión dura más que el apartado: quien la cierra a los 5 minutos es
     * tienda:limpiar-pendientes. Si aun así alguien paga tarde, ConfirmarCompraAction
     * confirma si sigue habiendo lugar y si no, reembolsa.
     */
    public const MINUTOS_SESION_STRIPE = 31;

    /**
     * Apartar y completar en un solo paso. Lo usa la pantalla anterior que quede
     * en caché (manda todo junto al pulsar Pagar).
     */
    public function ejecutar(array $datos): CompraPendiente
    {
        return $this->completar($this->apartar($datos), $datos);
    }

    /**
     * Aparta el horario elegido, sin datos del cliente todavía. Si el cliente
     * cambia de horario, el controlador suelta el apartado anterior antes de
     * llamar aquí: si no, sus propios lugares le contaban como ocupados.
     *
     * @param  Carbon|null  $vence  Vencimiento heredado del apartado anterior: cambiar
     *                              de horario no reinicia los minutos. Nunca más tarde
     *                              que un apartado nuevo.
     */
    public function apartar(array $datos, ?Carbon $vence = null): CompraPendiente
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

        // Sin código todavía: el total se recalcula en completar() con el que traiga.
        $precio = DescuentoAplicado::sinCodigo($producto, $personas);

        $tramos = empty($datos['horas'])
            ? $this->tramosDelHorario($producto, $sucursal, $datos['fecha'], $datos['hora'], $personas)
            : $this->tramosElegidos($producto, $sucursal, $datos['fecha'], $datos['horas'], $personas);

        // El apartado y la comprobación van en la misma transacción para que dos
        // clientes que eligen el mismo horario a la vez no se aparten los dos.
        $limite = Carbon::now()->addMinutes(self::MINUTOS_APARTADO);
        $vence = $vence && $vence->isFuture() && $vence->lt($limite) ? $vence : $limite;

        return DB::transaction(function () use ($producto, $sucursal, $personas, $tramos, $precio, $vence) {
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
                'total' => $precio->total(),
                'descuento' => 0,
                'estado' => 'apartada',
                'expira_en' => $vence,
            ]);
        });
    }

    /**
     * Pone los datos del cliente y el código sobre un apartado vigente, justo
     * antes de mandarlo a pagar. El precio se vuelve a calcular aquí: el código
     * se resuelve contra el catálogo, nunca se recibe calculado.
     *
     * @throws ValidationException si el apartado ya venció o el código no aplica.
     */
    public function completar(CompraPendiente $compra, array $datos): CompraPendiente
    {
        return DB::transaction(function () use ($compra, $datos) {
            $compra = CompraPendiente::query()->lockForUpdate()->findOrFail($compra->id);

            if (! $compra->vigente()) {
                throw ValidationException::withMessages([
                    'referencia' => 'Se acabó el tiempo para pagar y el horario se liberó. Elige uno de nuevo, por favor.',
                ]);
            }

            $producto = $compra->producto()->withoutGlobalScope(SucursalFilterScope::class)->firstOrFail();
            $descuento = DescuentoAplicado::resolver($datos['codigo_descuento'] ?? null, $producto, (int) $compra->personas);

            $compra->forceFill([
                'nombre' => $datos['nombre'],
                'email' => $datos['email'],
                'telefono' => $datos['telefono'] ?? null,
                'total' => $descuento->total(),
                'descuento' => $descuento->descuentoTotal(),
                'codigo_descuento' => $descuento->codigo,
                'descuento_id' => $descuento->id,
                'porcentaje_descuento' => $descuento->porcentaje,
                'clave_idempotencia' => $compra->clave_idempotencia ?? ($datos['clave_idempotencia'] ?? null),
            ])->save();

            return $compra;
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
