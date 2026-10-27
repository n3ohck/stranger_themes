<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Fechas con el día de la semana para el panel ("Jue 29 oct 2026"). El negocio
 * revisa los cortes por día de operación y el día de la semana es lo primero que
 * busca. Se escriben a mano porque translatedFormat('D') da "jue." con punto.
 *
 * Su espejo para el POS es diaCorto() en resources/js/pos/utils/formato.js.
 */
class Fechas
{
    private const DIAS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** "Jue 29 oct 2026" */
    public static function conDia($fecha): string
    {
        $f = Carbon::parse($fecha);

        return self::DIAS[$f->dayOfWeek] . ' ' . $f->day . ' ' . self::MESES[$f->month - 1] . ' ' . $f->year;
    }

    /** "Jue 29 oct 2026, 11:46 a.m." */
    public static function conDiaYHora($fecha): string
    {
        return self::conDia($fecha) . ', ' . self::hora($fecha);
    }

    /**
     * El turno de un corte: "Jue 29 oct 2026 · 11:46 a.m. – 9:05 p.m.", o con las
     * dos fechas si cruzó la medianoche.
     */
    public static function turno($desde, $hasta): string
    {
        if (! $hasta) {
            return self::conDiaYHora($desde);
        }

        $inicio = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);

        return $inicio->isSameDay($fin)
            ? self::conDia($inicio) . ' · ' . self::hora($inicio) . ' – ' . self::hora($fin)
            : self::conDiaYHora($inicio) . ' – ' . self::conDiaYHora($fin);
    }

    public static function hora($fecha): string
    {
        $f = Carbon::parse($fecha);

        return $f->format('g:i') . ($f->hour < 12 ? ' a.m.' : ' p.m.');
    }
}
