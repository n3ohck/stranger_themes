<?php

namespace App\Support\Tienda;

use App\Models\CompraPendiente;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Sucursal;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Calcula qué horarios puede comprar el cliente en el sitio web.
 *
 * Cada horario es exclusivo del grupo que lo reserva: si alguien aparta las 18:00
 * del Manicomio, esas 18:00 desaparecen para todos los demás aunque vayan dos
 * personas de las ocho que caben. La disponibilidad es binaria, no por lugares.
 *
 * Un paquete ocupa un tramo por cada recorrido que incluye, encadenados uno tras
 * otro. Solo se ofrece una hora de inicio si TODOS sus tramos están libres y caben
 * antes del cierre; de otro modo el cliente compraría un paquete que el negocio no
 * puede operar.
 */
class Disponibilidad
{
    /** Rejilla de horarios. Coincide con la operación histórica del negocio. */
    public const INTERVALO_MINUTOS = 30;

    /** Margen mínimo para poder comprar un horario de hoy. */
    public const ANTICIPACION_MINUTOS = 60;

    /**
     * @return array<int, array{inicio: string, etiqueta: string, tramos: array}>
     */
    public static function paraProducto(Producto $producto, Sucursal $sucursal, CarbonInterface $fecha): array
    {
        $recorridos = self::recorridosDe($producto);

        if ($recorridos->isEmpty()) {
            return [];
        }

        $jornada = self::jornadaDe($sucursal, $fecha);

        if (! $jornada) {
            return []; // La sucursal no abre ese día.
        }

        [$apertura, $cierre] = $jornada;

        $ocupados = self::horariosOcupados($sucursal, $recorridos->pluck('id')->all(), $fecha);
        $minimo = Carbon::now()->addMinutes(self::ANTICIPACION_MINUTOS);

        $disponibles = [];

        for ($inicio = $apertura->copy(); $inicio < $cierre; $inicio->addMinutes(self::INTERVALO_MINUTOS)) {
            if ($inicio->lt($minimo)) {
                continue;
            }

            $tramos = self::armarTramos($recorridos, $inicio);

            // El último tramo tiene que terminar antes del cierre.
            $fin = $inicio->copy()->addMinutes(self::INTERVALO_MINUTOS * $recorridos->count());

            if ($fin->gt($cierre)) {
                break;
            }

            $libre = collect($tramos)->every(
                fn ($tramo) => ! in_array($tramo['producto_id'] . '|' . $tramo['inicio'], $ocupados, true)
            );

            if ($libre) {
                $disponibles[] = [
                    'inicio' => $inicio->format('H:i'),
                    'etiqueta' => $inicio->format('g:i a'),
                    'tramos' => $tramos,
                ];
            }
        }

        return $disponibles;
    }

    /**
     * ¿Sigue libre esta combinación exacta de horarios? Se vuelve a preguntar justo
     * antes de cobrar, porque entre que el cliente eligió y llegó a pagar pudo
     * habérselo llevado alguien más.
     */
    public static function tramosSiguenLibres(Sucursal $sucursal, array $tramos, ?int $ignorarPendiente = null): bool
    {
        if (empty($tramos)) {
            return false;
        }

        $fecha = Carbon::parse($tramos[0]['inicio']);
        $productoIds = collect($tramos)->pluck('producto_id')->unique()->all();
        $ocupados = self::horariosOcupados($sucursal, $productoIds, $fecha, $ignorarPendiente);

        return collect($tramos)->every(
            fn ($tramo) => ! in_array($tramo['producto_id'] . '|' . $tramo['inicio'], $ocupados, true)
        );
    }

    /**
     * Recorridos que componen el producto, en orden. Un tour es uno solo; un paquete
     * son los de su columna `tours`.
     */
    public static function recorridosDe(Producto $producto): Collection
    {
        if ($producto->tipo === 'tour') {
            return collect([$producto]);
        }

        if ($producto->tipo !== 'tour_paquete') {
            return collect();
        }

        $ids = collect($producto->tours ?? [])
            ->pluck('producto_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $porId = Producto::query()->whereIn('id', $ids)->get()->keyBy('id');

        // Se conserva el orden declarado en el paquete, que es el orden del recorrido.
        return $ids->map(fn ($id) => $porId->get($id))->filter()->values();
    }

    private static function armarTramos(Collection $recorridos, CarbonInterface $inicio): array
    {
        return $recorridos->values()->map(fn (Producto $recorrido, int $i) => [
            'producto_id' => $recorrido->id,
            'producto' => $recorrido->descripcion,
            'inicio' => $inicio->copy()->addMinutes(self::INTERVALO_MINUTOS * $i)->format('Y-m-d H:i:s'),
        ])->all();
    }

    /**
     * Claves "producto|fecha hora" que ya no se pueden vender: reservas confirmadas
     * y apartados de compras que aún están en proceso de pago.
     */
    private static function horariosOcupados(Sucursal $sucursal, array $productoIds, CarbonInterface $fecha, ?int $ignorarPendiente = null): array
    {
        $desde = $fecha->copy()->startOfDay();
        $hasta = $fecha->copy()->endOfDay();

        $reservados = Reserva::query()
            ->withoutGlobalScopes()
            ->where('sucursal_id', $sucursal->id)
            ->whereIn('producto_id', $productoIds)
            ->where('estado', 'confirmada')
            ->whereBetween('fecha', [$desde, $hasta])
            ->get(['producto_id', 'fecha'])
            ->map(fn ($reserva) => $reserva->producto_id . '|' . Carbon::parse($reserva->fecha)->format('Y-m-d H:i:s'))
            // toBase() es necesario: Eloquent\Collection::map() solo degrada a
            // colección base si detecta algún elemento que no sea modelo, y con cero
            // reservas no detecta nada. Quedaría una colección de Eloquent llena de
            // strings, y su unique() llama getKey() sobre cada uno.
            ->toBase();

        $apartados = CompraPendiente::query()
            ->vigentes()
            ->where('sucursal_id', $sucursal->id)
            ->when($ignorarPendiente, fn ($query) => $query->where('id', '!=', $ignorarPendiente))
            ->get(['id', 'horarios'])
            ->flatMap(fn (CompraPendiente $pendiente) => collect($pendiente->horarios)
                ->map(fn ($tramo) => $tramo['producto_id'] . '|' . $tramo['inicio']))
            ->toBase();

        return $reservados->merge($apartados)->unique()->values()->all();
    }

    /**
     * Apertura y cierre de la sucursal para ese día, o null si no abre.
     *
     * Los días se guardan sin acento ('sabado', 'miercoles'), así que se comparan
     * normalizados para que un 'Sábado' capturado con acento no deje el día cerrado.
     */
    private static function jornadaDe(Sucursal $sucursal, CarbonInterface $fecha): ?array
    {
        $dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $hoy = $dias[$fecha->dayOfWeek];

        $jornada = collect($sucursal->horarios ?? [])
            ->first(fn ($h) => self::normalizar($h['dia'] ?? '') === $hoy);

        if (! $jornada || empty($jornada['hora_entrada']) || empty($jornada['hora_salida'])) {
            return null;
        }

        return [
            $fecha->copy()->setTimeFromTimeString($jornada['hora_entrada']),
            $fecha->copy()->setTimeFromTimeString($jornada['hora_salida']),
        ];
    }

    private static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
    }
}
