<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
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
