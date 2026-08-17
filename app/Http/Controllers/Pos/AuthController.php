<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePosUser;
use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Login del punto de venta.
     *
     * A diferencia del login del API viejo, el mensaje de error es el mismo para
     * usuario inexistente, contraseña incorrecta y rol insuficiente: distinguirlos
     * permitía enumerar qué cuentas existen.
     */
    public function login(Request $request)
    {
        $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'account.required' => 'Ingresa tu usuario o correo.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $credencialesInvalidas = response()->json([
            'message' => 'Usuario o contraseña incorrectos.',
        ], 401);

        try {
            $user = User::accountIs($request->input('account'))->first();

            if (! $user || ! $user->checkPassword($request->input('password'))) {
                return $credencialesInvalidas;
            }

            if (! $user->hasAnyRole(EnsurePosUser::ROLES) || ! $user->sucursal_id) {
                return $credencialesInvalidas;
            }

            $token = $user->makeToken();
        } catch (JWTException $e) {
            return response()->json(['message' => 'No se pudo generar la sesión. Intenta de nuevo.'], 500);
        }

        $user->load('sucursal');

        return response()->json([
            'token' => $token,
            'usuario' => [
                'id' => $user->id,
                'nombre' => $user->nombre_completo,
                'usuario' => $user->user,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'sucursal' => [
                    'id' => $user->sucursal->id ?? null,
                    'nombre' => $user->sucursal->razon_social ?? null,
                ],
            ],
        ]);
    }
}
