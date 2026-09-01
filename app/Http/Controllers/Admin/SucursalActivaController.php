<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SucursalActiva;
use Illuminate\Http\Request;

/**
 * Cambio de sucursal activa desde el panel.
 *
 * La elección vive en la sesión y solo la respetan los roles de supervisión;
 * SucursalActiva::elegir() rechaza cualquier otro caso, así que no basta con
 * publicar este formulario para saltarse el aislamiento por sucursal.
 */
class SucursalActivaController extends Controller
{
    public function cambiar(Request $request)
    {
        $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
        ]);

        if (! SucursalActiva::elegir((int) $request->input('sucursal_id'))) {
            \Alert::error('No tienes permiso para cambiar de sucursal.')->flash();

            return back();
        }

        return back();
    }
}
