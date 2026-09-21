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
    /**
     * Intervalo por omisión, solo para sucursales que todavía no lo tienen capturado.
     * El valor real sale de cada día del horario de la sucursal, porque una sucursal
     * puede sacar recorridos cada 30 minutos y otra cada hora.
     */
    public const INTERVALO_POR_OMISION = 30;

    /** Margen mínimo para poder comprar un horario de hoy. */
    public const ANTICIPACION_MINUTOS = 60;

    /**
     * @return array<int, array{inicio: string, etiqueta: string, tramos: array}>
     */
    public static function paraProducto(
        Producto $producto,
        Sucursal $sucursal,
        CarbonInterface $fecha,
        ?int $anticipacionMinutos = null
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

        $ocupados = self::horariosOcupados($sucursal, $recorridos->pluck('id')->all(), $fecha);
        // El sitio web exige margen para que nadie compre una salida inminente.
        // El mostrador vende la siguiente salida, así que ahí se pasa 0.
        $minimo = Carbon::now()->addMinutes($anticipacionMinutos ?? self::ANTICIPACION_MINUTOS);

        $disponibles = [];

        for ($inicio = $apertura->copy(); $inicio < $cierre; $inicio->addMinutes($intervalo)) {
            if ($inicio->lt($minimo)) {
                continue;
            }

            $tramos = self::armarTramos($recorridos, $inicio, $intervalo);

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

    private static function armarTramos(Collection $recorridos, CarbonInterface $inicio, int $intervalo): array
    {
        return $recorridos->values()->map(fn (Producto $recorrido, int $i) => [
            'producto_id' => $recorrido->id,
            'producto' => $recorrido->descripcion,
            'inicio' => $inicio->copy()->addMinutes($intervalo * $i)->format('Y-m-d H:i:s'),
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
