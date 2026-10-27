<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Apertura;
use App\Models\Corte;
use App\Support\Caja\ResumenCorte;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function abrir(Request $request)
    {
        $request->validate([
            'monto_apertura' => ['required', 'numeric', 'min:0'],
        ], [
            'monto_apertura.required' => 'Indica el fondo con el que abres la caja.',
            'monto_apertura.numeric' => 'El fondo de apertura debe ser un número.',
            'monto_apertura.min' => 'El fondo de apertura no puede ser negativo.',
        ]);

        $user = Auth::user();

        if (Apertura::aperturaActiva($user)) {
            return response()->json([
                'message' => 'Ya tienes una caja abierta. Realiza el corte antes de abrir otra.',
            ], 409);
        }

        $apertura = Apertura::create([
            'user_id' => $user->id,
            'sucursal_id' => $user->sucursal_id,
            'monto_apertura' => $request->input('monto_apertura'),
            'estado' => 'abierto',
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Caja abierta.',
            'apertura' => [
                'id' => $apertura->id,
                'monto_apertura' => (float) $apertura->monto_apertura,
                'abierta_en' => (string) $apertura->created_at,
                'estado' => $apertura->estado,
            ],
        ], 201);
    }

    /**
     * Totales del turno calculados por el servidor.
     *
     * En el flujo anterior el POS mandaba estos importes ya sumados y el backend
     * los guardaba sin verificar: si el cliente sumaba mal, el corte quedaba mal
     * y nadie se enteraba. Ahora el POS solo declara el efectivo contado en caja
     * y el servidor calcula todo lo demás.
     */
    public function precorte()
    {
        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        return response()->json(['precorte' => ResumenCorte::precorte($apertura, (int) Auth::user()->sucursal_id)]);
    }

    public function cerrar(Request $request)
    {
        $request->validate([
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'fondo_final' => ['nullable', 'numeric', 'min:0'],
        ], [
            'efectivo_contado.required' => 'Indica cuánto efectivo hay físicamente en la caja.',
        ]);

        $user = Auth::user();
        $apertura = Apertura::aperturaActiva($user);

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        $precorte = ResumenCorte::precorte($apertura, (int) Auth::user()->sucursal_id);

        // El ticket firmado es la única constancia de que el empleado recibió el
        // dinero; una vez cerrado el turno ya no se puede subir desde el POS.
        $sinFirma = $precorte['pendientes']['pagos_sin_firma'];

        if ($sinFirma > 0) {
            return response()->json([
                'message' => $sinFirma === 1
                    ? 'Hay un pago a empleado sin la foto del ticket firmado. Súbela en Movimientos antes de hacer el corte.'
                    : "Hay {$sinFirma} pagos a empleado sin la foto del ticket firmado. Súbelas en Movimientos antes de hacer el corte.",
            ], 422);
        }

        $corte = DB::transaction(function () use ($request, $apertura, $precorte, $user) {
            $corte = Corte::create([
                'total' => $precorte['ventas']['total'],
                'efectivo' => $precorte['ventas']['efectivo'],
                'tarjeta' => $precorte['ventas']['tarjeta'],
                'transferencia' => $precorte['ventas']['transferencia'],
                'total_online' => $precorte['ventas']['online'],
                'total_caja' => $request->input('efectivo_contado'),
                'fondo_final' => $request->input('fondo_final') ?? 0,
                'fecha_inicio' => $apertura->created_at,
                'fecha_final' => Carbon::now(),
                'user_id' => $user->id,
                'sucursal_id' => $user->sucursal_id,
                'apertura_id' => $apertura->id,
            ]);

            $apertura->estado = 'cerrado';
            $apertura->user_id_cerro = $user->id;
            $apertura->monto_cierre = $corte->total_caja;
            $apertura->save();

            return $corte;
        });

        return response()->json([
            'message' => 'Corte realizado.',
            'corte' => ResumenCorte::deCorte($corte, $precorte),
        ], 201);
    }

    public function ticketCorte(Corte $corte)
    {
        return response()->json(['corte' => ResumenCorte::deCorte($corte)]);
    }
}
