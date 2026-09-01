<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    /**
     * Sucursales que el sitio web puede ofrecer.
     *
     * Con más de una sucursal operando, el sitio necesita listarlas para que el
     * cliente elija antes de ver catálogo y horarios. Devuelve solo lo que el
     * sitio necesita pintar: nada de prefijos de folio ni consecutivos, que son
     * internos de la operación.
     */
    public function publicIndex()
    {
        $sucursales = Sucursal::query()
            ->orderBy('razon_social')
            ->get()
            ->map(fn (Sucursal $sucursal) => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->razon_social,
                'direccion' => $sucursal->direccion,
                'telefono' => $sucursal->telefono,
                'email' => $sucursal->email,
                'horarios' => $sucursal->horarios,
                'ubicacion' => $sucursal->ubicacion,
                'logotipo' => $sucursal->logotipo,
            ]);

        return response()->json([
            'sucursales' => $sucursales->values(),
            'qty' => $sucursales->count(),
        ]);
    }

    public function getByBranch(Request $request)
    {
        try {
            $branchId = $request->get('sucursal_id');
            if (!$branchId) throw new \Exception('No se ha proporcionado una sucursal.');
            $sucursal = Sucursal::query()->where('id', $branchId)->first();
            if (!$sucursal) throw new \Exception('No se ha encontrado la sucursal.');
            return response()->json([
                'message' => 'Consuta exitosa.',
                'sucursal' => $sucursal
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
