<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DisputaJob;
use App\Models\Disputa;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputasController extends Controller
{
    public function set(Request $request)
    {
        try {
            $validator = $request->validate([
                'venta_id' => 'required|exists:ventas,id'
            ]);
            DB::beginTransaction();
            $venta = Venta::find($request->input('venta_id'));
            $venta->estatus = 'cancelado';
            $venta->reservaciones()->each(function ($reservacion) {
                $reservacion->estado = 'cancelada';
                $reservacion->save();
            });
            $venta->save();
            $disputa = new Disputa();
            $disputa->venta_id = $venta->id;
            $disputa->save();
            DisputaJob::dispatch($venta->id);
            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Disputa creada correctamente',
                'data' => $disputa
            ]);
        }catch (ValidationException $e){
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->validator->errors()
            ]);
        }catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
