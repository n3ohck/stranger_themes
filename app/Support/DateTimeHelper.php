<?php
namespace App\Support;

use Carbon\Carbon;

class DateTimeHelper
{
    /**
     * Convierte un input de fecha/hora (con o sin TZ) a UTC para consultas.
     * - Si el input es naive (sin TZ), asume config('app.input_naive_timezone', 'UTC').
     * - Si es solo fecha (YYYY-MM-DD), normaliza inicio/fin de día en la TZ de display.
     */
    public function toUtcForQuery(string $input, bool $isEnd = false): Carbon
    {
        $displayTz = config('app.display_timezone', 'America/Chihuahua');
        $inputNaiveTz = config('app.input_naive_timezone', 'UTC'); // <— define esto

        $s = trim($input);

        // 1) ¿Trae zona horaria explícita? (Z o ±HH:MM)
        $hasTz = (bool) preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $s);

        // 2) Parse con la TZ correcta
        $dt = $hasTz
            ? Carbon::parse($s)                      // respeta el offset del string
            : Carbon::parse($s, $inputNaiveTz);      // asume UTC (o la que configures)

        // 3) Si viene solo la fecha (YYYY-MM-DD), normaliza a inicio/fin de día LOCAL
        $onlyDate = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
        if ($onlyDate) {
            $dt = $dt->setTimezone($displayTz);
            $dt = $isEnd ? $dt->endOfDay() : $dt->startOfDay();
        }

        // 4) Devuelve en UTC para consultar en DB
        return $dt->clone()->setTimezone('UTC');
    }

    /**
     * Convierte un Carbon/fecha (UTC o con TZ) a la TZ de display.
     */
    public static function toDisplayTz($value): ?Carbon
    {
        if (!$value) return null;
        $displayTz = config('app.display_timezone', 'America/Chihuahua');
        $c = $value instanceof Carbon ? $value : Carbon::parse($value);
        return $c->clone()->setTimezone($displayTz);
    }
}
