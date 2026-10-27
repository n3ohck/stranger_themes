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
 * Calcula qué horarios se pueden vender y cuántos lugares les quedan.
 *
 * Un horario se comparte entre grupos: si una pareja tiene las 17:00 de Winchester
 * y llegan tres amigos, entran los cinco juntos. La ocupación se cuenta en
 * personas (reservas confirmadas más compras web apartadas) contra la capacidad
 * del recorrido.
 *
 * El cupo solo se hace respetar en el sitio web. En mostrador se puede meter más
 * gente: llegan 10 niños a un recorrido de 8 que sí caben, y hay que poder
 * registrarlos y pasarlos juntos. Por eso paraProducto() recibe las personas del
 * grupo, o null para no filtrar por cupo.
 *
 * Un paquete ocupa un tramo por cada recorrido que incluye, encadenados uno tras
 * otro. En línea solo se ofrece una hora de inicio si el grupo cabe en TODOS sus
 * tramos y terminan antes del cierre.
 */
class Disponibilidad
{
    /**
     * Intervalo por omisión, solo para sucursales que todavía no lo tienen capturado.
     * El valor real sale de cada día del horario de la sucursal, porque una sucursal
     * puede sacar recorridos cada 30 minutos y otra cada hora.
     */
    public const INTERVALO_POR_OMISION = 30;

    /** Margen mínimo para poder comprar un horario de hoy. */
    public const ANTICIPACION_MINUTOS = 60;

    /**
     * Cada tramo trae su capacidad y cuántas personas lleva ya; `lugares` es lo
     * que le queda al tramo más lleno (null si ningún recorrido tiene capacidad).
     *
     * @param  int|null  $personas  Tamaño del grupo. Con null no se filtra por cupo
     *                              (mostrador); con un número solo se devuelven los
     *                              horarios donde el grupo cabe en todos los tramos.
     * @return array<int, array{inicio: string, etiqueta: string, lugares: ?int, tramos: array}>
     */
    public static function paraProducto(
        Producto $producto,
        Sucursal $sucursal,
        CarbonInterface $fecha,
        ?int $anticipacionMinutos = null,
        ?int $personas = 1
    ): array {
        $recorridos = self::recorridosDe($producto);

        if ($recorridos->isEmpty()) {
            return [];
        }

        $jornada = self::jornadaDe($sucursal, $fecha);

        if (! $jornada) {
            return []; // La sucursal no abre ese día.
        }

        [$apertura, $cierre, $intervalo] = $jornada;

        $ocupacion = self::ocupacion($sucursal, $recorridos->pluck('id')->all(), $fecha);
        // El sitio web exige margen para que nadie compre una salida inminente.
        // El mostrador vende la siguiente salida, así que ahí se pasa 0.
        $minimo = Carbon::now()->addMinutes($anticipacionMinutos ?? self::ANTICIPACION_MINUTOS);

        $disponibles = [];

        for ($inicio = $apertura->copy(); $inicio < $cierre; $inicio->addMinutes($intervalo)) {
            if ($inicio->lt($minimo)) {
                continue;
            }

            $tramos = self::armarTramos($recorridos, $inicio, $intervalo, $ocupacion);

            // El último recorrido tiene que terminar antes del cierre.
            //
            // Se suma su duración real, no el intervalo: el intervalo es cada cuánto
            // sale un recorrido, no cuánto dura. Con salidas cada hora y recorridos de
            // 25 minutos, la salida de las 21:30 termina 21:55 y es perfectamente
            // vendible; calcularlo con el intervalo la descartaba sin razón.
            $duracionUltimo = (int) ($recorridos->last()->duracion_minutos ?: $intervalo);

            $fin = $inicio->copy()
                ->addMinutes($intervalo * ($recorridos->count() - 1))
                ->addMinutes($duracionUltimo);

            if ($fin->gt($cierre)) {
                break;
            }

            $lugares = self::lugaresDe($tramos);

            if ($personas !== null && $lugares !== null && $lugares < $personas) {
                continue;
            }

            $disponibles[] = [
                'inicio' => $inicio->format('H:i'),
                'etiqueta' => $inicio->format('g:i a'),
                'lugares' => $lugares,
                'tramos' => $tramos,
            ];
        }

        return $disponibles;
    }

    /**
     * ¿Sigue cabiendo el grupo en estos tramos? Se vuelve a preguntar justo antes
     * de apartar, porque entre que el cliente eligió y llegó a pagar otros pudieron
     * llenar el horario. Solo lo usa el sitio web: el mostrador puede pasarse.
     */
    public static function tramosConCupo(Sucursal $sucursal, array $tramos, int $personas, ?int $ignorarPendiente = null): bool
    {
        if (empty($tramos)) {
            return false;
        }

        $capacidades = Producto::query()
            ->withoutGlobalScopes()
            ->whereIn('id', collect($tramos)->pluck('producto_id')->unique())
            ->pluck('capacidad', 'id');

        return collect($tramos)->every(function ($tramo) use ($sucursal, $capacidades, $personas, $ignorarPendiente) {
            $capacidad = (int) ($capacidades[$tramo['producto_id']] ?? 0);

            if ($capacidad <= 0) {
                return true;
            }

            $ocupacion = self::ocupacion($sucursal, [$tramo['producto_id']], Carbon::parse($tramo['inicio']), $ignorarPendiente);
            $ocupados = $ocupacion[$tramo['producto_id'] . '|' . $tramo['inicio']] ?? 0;

            return $ocupados + $personas <= $capacidad;
        });
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

    private static function armarTramos(Collection $recorridos, CarbonInterface $inicio, int $intervalo, array $ocupacion): array
    {
        return $recorridos->values()->map(function (Producto $recorrido, int $i) use ($inicio, $intervalo, $ocupacion) {
            $cuando = $inicio->copy()->addMinutes($intervalo * $i)->format('Y-m-d H:i:s');

            return [
                'producto_id' => $recorrido->id,
                'producto' => $recorrido->descripcion,
                'inicio' => $cuando,
                'capacidad' => $recorrido->capacidad ? (int) $recorrido->capacidad : null,
                'ocupados' => $ocupacion[$recorrido->id . '|' . $cuando] ?? 0,
            ];
        })->all();
    }

    /** Lugares del tramo más lleno; null si ninguno tiene capacidad capturada. */
    private static function lugaresDe(array $tramos): ?int
    {
        $lugares = collect($tramos)
            ->filter(fn ($tramo) => $tramo['capacidad'])
            ->map(fn ($tramo) => $tramo['capacidad'] - $tramo['ocupados']);

        return $lugares->isEmpty() ? null : max(0, $lugares->min());
    }

    /**
     * Personas que ya ocupan cada horario, por clave "producto|fecha hora": reservas
     * confirmadas más los apartados de compras web que aún están en proceso de pago.
     *
     * @return array<string, int>
     */
    private static function ocupacion(Sucursal $sucursal, array $productoIds, CarbonInterface $fecha, ?int $ignorarPendiente = null): array
    {
        $desde = $fecha->copy()->startOfDay();
        $hasta = $fecha->copy()->endOfDay();
        $porClave = [];

        Reserva::query()
            ->withoutGlobalScopes()
            ->where('sucursal_id', $sucursal->id)
            ->whereIn('producto_id', $productoIds)
            ->where('estado', 'confirmada')
            ->whereBetween('fecha', [$desde, $hasta])
            ->get(['producto_id', 'fecha', 'cantidad_personas'])
            ->each(function ($reserva) use (&$porClave) {
                $clave = $reserva->producto_id . '|' . Carbon::parse($reserva->fecha)->format('Y-m-d H:i:s');
                $porClave[$clave] = ($porClave[$clave] ?? 0) + max(1, (int) $reserva->cantidad_personas);
            });

        CompraPendiente::query()
            ->vigentes()
            ->where('sucursal_id', $sucursal->id)
            ->when($ignorarPendiente, fn ($query) => $query->where('id', '!=', $ignorarPendiente))
            ->get(['id', 'horarios', 'personas'])
            ->each(function (CompraPendiente $pendiente) use (&$porClave) {
                foreach ($pendiente->horarios ?? [] as $tramo) {
                    $clave = $tramo['producto_id'] . '|' . $tramo['inicio'];
                    $porClave[$clave] = ($porClave[$clave] ?? 0) + max(1, (int) $pendiente->personas);
                }
            });

        return $porClave;
    }

    /**
     * Intervalo configurado para ese día, en minutos.
     */
    public static function intervaloDe(Sucursal $sucursal, CarbonInterface $fecha): int
    {
        $jornada = self::jornadaDe($sucursal, $fecha);

        return $jornada ? $jornada[2] : self::INTERVALO_POR_OMISION;
    }

    /**
     * Todos los horarios en que puede salir un recorrido ese día, estén libres u
     * ocupados. La lista de reservas la usa para pintar la rejilla completa: el
     * operador necesita ver los huecos, no solo lo vendido.
     *
     * @return array<int, string> ['15:30', '16:00', ...]
     */
    public static function rejillaDelDia(Sucursal $sucursal, CarbonInterface $fecha): array
    {
        $jornada = self::jornadaDe($sucursal, $fecha);

        if (! $jornada) {
            return [];
        }

        [$apertura, $cierre, $intervalo] = $jornada;

        $horas = [];

        for ($inicio = $apertura->copy(); $inicio < $cierre; $inicio->addMinutes($intervalo)) {
            $horas[] = $inicio->format('H:i');
        }

        return $horas;
    }

    /**
     * Apertura, cierre e intervalo de la sucursal para ese día, o null si no abre.
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

        $intervalo = (int) ($jornada['intervalo_minutos'] ?? 0);

        return [
            $fecha->copy()->setTimeFromTimeString($jornada['hora_entrada']),
            $fecha->copy()->setTimeFromTimeString($jornada['hora_salida']),
            $intervalo > 0 ? $intervalo : self::INTERVALO_POR_OMISION,
        ];
    }

    private static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
    }
}
