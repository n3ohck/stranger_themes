<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservasController extends Controller
{
    public function fetch(Request $request)
    {
        try {
            $productoId = $request->get('producto_id');
            $date = $request->get('date');
            $sucursalId = $request->get('sucursal_id');
            if( !$productoId ){
                throw new \Exception("No se encontro el producto");
            }
            if( !$date ){
                throw new \Exception("No se encontro la fecha");
            }
            if( !$sucursalId ){
                throw new \Exception("No se encontro la sucursal");
            }
            $startDate = Carbon::parse($date)->startOfDay();
            $endDate = Carbon::parse($date)->endOfDay();
            $reservas = Reserva::query()
                ->withoutGlobalScopes()
                ->where('sucursal_id', $sucursalId)
                ->where('producto_id', $productoId)
                ->whereBetween('fecha', [
                    $startDate,
                    $endDate
                ])
                ->where('estado', 'confirmada')
                ->get()
                ->map(function ($reservacion){
                    return [
                        'id' => $reservacion->id,
                        'datetime' => Carbon::parse($reservacion->fecha)->format('H:i')
                    ];
                })
                ->groupBy('datetime')
                ->map(function ($reservaciones,$key){
                    return[
                        'time' => $key,
                        'qty' => $reservaciones->count()
                    ];
                });

            return response()->json([
                'message' => 'Consulta realizada con exito',
                'reservas' => $reservas,
                'qty' => $reservas->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
