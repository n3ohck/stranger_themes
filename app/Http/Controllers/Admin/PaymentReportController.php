<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Corte;
use App\Models\Egreso;
use App\Models\EmpleadoPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Prologue\Alerts\Facades\Alert;

class PaymentReportController extends Controller
{
    public function view(Corte $corte)
    {
        if ($corte->id){
            $allowedOrigins = ['empleado', 'egreso'];
            $ultimoSegmento = request()->segment(count(request()->segments()));

            if (!in_array($ultimoSegmento, $allowedOrigins)) {
                Alert::warning('El origen no es valido')->flash();
                return redirect()->back();
            }
            if( is_null($ultimoSegmento) ){
                Alert::warning('El origen no es valido')->flash();
                return redirect()->back();
            }
            $startDate = Carbon::parse($corte->fecha_inicio);
            $endDate = Carbon::parse($corte->fecha_fin);
            return Inertia::render('Reportes/index', [
                'corte' => $corte,
                'origin' => $ultimoSegmento,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
                'branch' => $corte->sucursal_id,
            ]);
        }
        return Inertia::render('Reportes/index');
    }

    public function egreso($startDate, $endDate, int $branch)
    {
        return Egreso::query()
            ->select(['monto', 'fecha_pago', 'estatus', 'sucursal_id','imagen','user_id'])
            ->whereHas('user')
            ->with(['user:id,user', 'sucursal:id,razon_social'])
            ->where(function ($query) use ($startDate, $endDate, $branch) {
                $query
                    ->whereBetween('fecha_pago', [$startDate, $endDate])
                    ->where('sucursal_id', $branch)
                    ->where('estatus', 'activo');
            })
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

    public function pagoEmpleado($startDate, $endDate, int $branch)
    {
        return EmpleadoPago::query()
            ->with(['empleado:id,nombres,apellidos', 'empleado.sucursal:id,razon_social'])
            ->whereHas('empleado', function ($q) use ($branch) {
                $q->where('sucursal_id', $branch);
            })
            ->where(function ($query) use ($startDate, $endDate, $branch) {
                $query
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('estatus', 'activo');
            })
            ->get()
            ->map(function($employee){
                return [
                    'type' => 'Pago Empleado',
                    'amount' => $employee->monto,
                    'date' => Carbon::parse($employee->created_at)->format('Y-m-d H:i:s'),
                    'user' => ( !isset( $employee->empleado ) ) ? 'N/A' : $employee->empleado->nombres . ' ' . $employee->empleado->apellidos,
                    'branch' =>  ( !isset( $employee->empleado ) ) ? 'N/A' : $employee->empleado->sucursal->razon_social,
                    'image' => asset('storage/pagos/' . $employee->imagen)
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

            $startDate = Carbon::parse($dates[0])->startOfDay()->format('Y-m-d H:i:s');
            $endDate = Carbon::parse($dates[1])->endOfDay()->format('Y-m-d H:i:s');

            $totals = [
                'pagoEmpleado' => 0,
                'egreso' => 0,
            ];
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
                $totals[$origin] = $data->pluck('amount')->sum();
                $datas->push($data);
            }

            return response()->json([
                'status' => true,
                'data' => $datas->flatten(1)->sortByDesc('date'),
                'totals' => $totals,
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
