<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateTrait
{
    public function toUtcForQuery(string $input, bool $isEnd = false): Carbon
    {
        $displayTz = config('app.display_timezone', 'America/Chihuahua');
        $inputNaiveTz = config('app.input_naive_timezone', 'UTC'); // <— define esto

        $s = trim($input);

        // 1) ¿Trae zona horaria explícita? (Z o ±HH:MM)
        $hasTz = (bool) preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $s);

        // 2) Parse con la TZ correcta
        $dt = Carbon::parse($s);

        // 3) Si viene solo la fecha (YYYY-MM-DD), normaliza a inicio/fin de día LOCAL
        $onlyDate = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
        if ($onlyDate) {
            $dt = $dt->setTimezone($displayTz);
            $dt = $isEnd ? $dt->endOfDay() : $dt->startOfDay();
        }

        // 4) Devuelve en UTC para consultar en DB
        return $dt->clone()->setTimezone('UTC');
    }

    public function makeDate($date)
    {
        $date = $this->toUtcForQuery($date);
        return $date;
    }
}
