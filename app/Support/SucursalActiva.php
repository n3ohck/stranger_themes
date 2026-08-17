<?php

namespace App\Support;

use App\Models\Sucursal;

/**
 * Resuelve sobre qué sucursal está trabajando el usuario en este momento.
 *
 * Reglas:
 *  - Un cajero o usuario de consulta siempre trabaja en la sucursal de su ficha.
 *  - Administración y gerencia pueden cambiar de sucursal; la elección vive en la
 *    sesión del panel y, si no han elegido nada, se usa la suya.
 *  - En el punto de venta no hay sesión (la autenticación es por JWT), así que la
 *    elección nunca aplica y todos quedan fijos en su propia sucursal. Eso es
 *    deliberado: un cajero no debe poder vender a nombre de otra sucursal.
 */
class SucursalActiva
{
    public const LLAVE_SESION = 'sucursal_activa';

    /**
     * Sucursal por la que se debe filtrar, o null si no hay usuario autenticado
     * (rutas públicas, que reciben y validan la sucursal por su cuenta).
     */
    public static function id(): ?int
    {
        $user = backpack_user();

        if (! $user) {
            return null;
        }

        if (Roles::supervisaSucursales($user)) {
            $elegida = self::elegida();

            if ($elegida) {
                return $elegida;
            }
        }

        return $user->sucursal_id ? (int) $user->sucursal_id : null;
    }

    /**
     * Sucursal seleccionada a mano en el panel, si la hay.
     */
    public static function elegida(): ?int
    {
        $request = request();

        // Las peticiones del POS no pasan por el middleware de sesión.
        if (! $request || ! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::LLAVE_SESION);

        return $id ? (int) $id : null;
    }

    /**
     * Guarda la sucursal elegida. Solo tiene efecto para quien supervisa varias.
     */
    public static function elegir(int $sucursalId): bool
    {
        $user = backpack_user();

        if (! Roles::supervisaSucursales($user)) {
            return false;
        }

        if (! Sucursal::query()->whereKey($sucursalId)->exists()) {
            return false;
        }

        request()->session()->put(self::LLAVE_SESION, $sucursalId);

        return true;
    }

    /**
     * Sucursales entre las que puede moverse el usuario. Quien no supervisa solo
     * tiene la suya, y así el selector ni siquiera se muestra.
     */
    public static function disponibles()
    {
        $user = backpack_user();

        if (! $user) {
            return collect();
        }

        if (Roles::supervisaSucursales($user)) {
            return Sucursal::query()->orderBy('razon_social')->get(['id', 'razon_social']);
        }

        return Sucursal::query()->whereKey($user->sucursal_id)->get(['id', 'razon_social']);
    }
}
