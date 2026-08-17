<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe el punto de venta a los roles operativos.
 *
 * Corre después de jwt.verify, que ya autenticó al usuario sobre el guard por
 * defecto vía Auth::onceUsingId(). Aquí solo se valida el rol y que el usuario
 * tenga sucursal asignada: sin sucursal, el scope global de multi-sucursal no
 * tiene por dónde filtrar y el cajero vería datos de todas las sucursales.
 */
class EnsurePosUser
{
    public const ROLES = Roles::PUNTO_DE_VENTA;

    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (! $user->hasAnyRole(self::ROLES)) {
            return response()->json([
                'message' => 'Tu usuario no tiene permisos para operar el punto de venta.',
            ], 403);
        }

        if (! $user->sucursal_id) {
            return response()->json([
                'message' => 'Tu usuario no tiene una sucursal asignada. Contacta al administrador.',
            ], 403);
        }

        return $next($request);
    }
}
