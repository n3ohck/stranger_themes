<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Apertura;
use Illuminate\Support\Facades\Auth;

class SesionController extends Controller
{
    /**
     * Todo lo que el POS necesita saber al arrancar: quién es, dónde está y si
     * tiene caja abierta. El SPA llama a esto en cada recarga para reconstruir
     * el estado sin confiar en lo que traiga guardado el navegador.
     */
    public function show()
    {
        $user = Auth::user()->load('sucursal');
        $apertura = Apertura::aperturaActiva($user);

        return response()->json([
            'usuario' => [
                'id' => $user->id,
                'nombre' => $user->nombre_completo,
                'usuario' => $user->user,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
            'sucursal' => [
                'id' => $user->sucursal->id ?? null,
                'nombre' => $user->sucursal->razon_social ?? null,
            ],
            'apertura' => $apertura ? $this->presentarApertura($apertura) : null,
        ]);
    }

    private function presentarApertura(Apertura $apertura): array
    {
        return [
            'id' => $apertura->id,
            'monto_apertura' => (float) $apertura->monto_apertura,
            'abierta_en' => (string) $apertura->created_at,
            'estado' => $apertura->estado,
        ];
    }
}
