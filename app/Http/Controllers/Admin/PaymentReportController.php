<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Egreso;
use App\Models\EmpleadoPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentReportController extends Controller
{
    public function view()
    {
        return Inertia::render('Reportes/index');
    }

    public function egreso(Carbon $startDate, Carbon $endDate, int $branch)
    {
        return Egreso::query()
            ->whereHas('user')
            ->with(['user:id,user', 'sucursal:id,razon_social'])
            ->select(['monto', 'fecha_pago', 'estatus', 'sucursal_id','imagen'])
            ->whereBetween('fecha_pago', [$startDate, $endDate])
            ->where('sucursal_id', $branch)
            ->where('estatus', 'activo')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Egreso',
                    'amount' => $item->monto,
                    'date' => $item->fecha_pago,
                    'user' => ( !isset($item->user)) ? 'N/A' : $item->user->user,
                    'branch' => $item->sucursal->razon_social,
                    'image' =>  asset('storage/pagos/' . $item->imagen)
                ];
            });
    }

    public function pagoEmpleado(Carbon $startDate, Carbon $endDate, int $branch)
    {
        return EmpleadoPago::query()
            ->with(['empleado:id,nombres,apellidos', 'empleado.sucursal:id,razon_social'])
            ->whereHas('empleado', function ($q) use ($branch) {
                $q->where('sucursal_id', $branch);
            })
            ->select(['monto', 'fecha_pago', 'estatus','imagen'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('estatus', 'activo')
            ->get()
            ->map(function($employee){
                return [
                    'type' => 'Pago Empleado',
                    'amount' => $employee->monto,
                    'date' => $employee->fecha_pago->format('Y-m-d H:i:s'),
                    'user' => ( !isset( $employee->empleado ) ) ? 'N/A' : $employee->empleado->nombres . ' ' . $employee->empleado->apellidos,
                    'branch' =>  ( !isset( $employee->empleado ) ) ? 'N/A' : $employee->empleado->sucursal->razon_social,
                    'image' => $employee->imagen
                ];
            });
    }


    public function fetch(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch' => 'required|integer|exists:sucursales,id',
                'dates' => 'required|array|size:2',
                'dates.0' => 'required|date',
                'dates.1' => 'required|date|after_or_equal:dates.0',
                'origins' => 'required|array|min:1',
                'origins.*' => 'string'
            ]);

            $dates = $request->input('dates');
            $origins = $request->input('origins');
            $branch = $request->input('branch');

            $startDate = Carbon::parse($dates[0]);
            $endDate = Carbon::parse($dates[1]);

            $datas = collect();
            foreach ($origins as $origin) {
                if (!method_exists($this, $origin)) {
                    throw new \Exception("El origen no existe");
                }
                $data = $this->$origin(
                    $startDate,
                    $endDate,
                    $branch
                );
                $datas->push($data);
            }

            return response()->json([
                'status' => true,
                'data' => $datas->flatten(1)->sortByDesc('date')
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }
}
