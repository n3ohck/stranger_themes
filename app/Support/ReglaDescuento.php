<?php

namespace App\Support;

/**
 * Regla de redondeo de descuentos acordada con el negocio.
 *
 * Aplica ÚNICAMENTE al descuento unitario en dinero. Precios y cantidades nunca
 * se redondean: el precio con descuento se deriva restando el descuento ya
 * redondeado, de modo que el ticket y el reporte siempre cuadran.
 *
 *   decimal <= .49  -> hacia abajo
 *   decimal == .50  -> se queda igual
 *   decimal >= .51  -> hacia arriba
 *
 * Esta lógica vivía duplicada como closure en dos métodos de VentaCrudController.
 */
class ReglaDescuento
{
    public static function redondear(float $valor): float
    {
        $signo = $valor < 0 ? -1 : 1;
        $absoluto = abs($valor);
        $decimal = $absoluto - floor($absoluto);

        if ($decimal <= 0.49) {
            $resultado = floor($absoluto);
        } elseif ($decimal >= 0.51) {
            $resultado = ceil($absoluto);
        } else {
            $resultado = $absoluto;
        }

        return $resultado * $signo;
    }

    /**
     * Descuento unitario en dinero para un precio y un porcentaje dados.
     */
    public static function unitario(float $precio, float $porcentaje): float
    {
        if ($precio <= 0 || $porcentaje <= 0) {
            return 0.0;
        }

        return max(0.0, self::redondear($precio * ($porcentaje / 100)));
    }
}
