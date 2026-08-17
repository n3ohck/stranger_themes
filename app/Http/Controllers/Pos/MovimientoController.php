<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Apertura;
use App\Models\Egreso;
use App\Models\Empleado;
use App\Models\EmpleadoPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Salidas de dinero del turno: egresos y pagos a empleados.
 *
 * Ambos se registran con la apertura activa y con la fecha del momento. El POS
 * no deja elegir fecha a propósito: lo que se captura aquí es dinero saliendo
 * del cajón ahora, y ese es el movimiento que el corte tiene que cuadrar. Un
 * gasto de otro día se captura desde el panel de administración.
 */
class MovimientoController extends Controller
{
    public function index()
    {
        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        $egresos = Egreso::query()
            ->where('apertura_id', $apertura->id)
            ->where('estatus', 'activo')
            ->orderByDesc('id')
            ->get();

        $pagos = EmpleadoPago::query()
            ->where('apertura_id', $apertura->id)
            ->where('estatus', 'activo')
            ->with('empleado')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'egresos' => $egresos->map(fn (Egreso $egreso) => [
                'id' => $egreso->id,
                'monto' => (float) $egreso->monto,
                'descripcion' => $egreso->descripcion,
                'tipo_pago' => $egreso->tipo_pago,
                'referencia' => $egreso->referencia,
                'fecha' => (string) $egreso->fecha_pago,
                'comprobante' => $this->urlComprobante($egreso->imagen),
            ])->values(),

            'pagos' => $pagos->map(fn (EmpleadoPago $pago) => [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'empleado' => trim(optional($pago->empleado)->nombres . ' ' . optional($pago->empleado)->apellidos),
                'fecha' => (string) $pago->fecha_pago,
                'comprobante' => $this->urlComprobante($pago->imagen),
            ])->values(),

            'totales' => [
                'egresos_efectivo' => round((float) $egresos->where('tipo_pago', 'efectivo')->sum('monto'), 2),
                'egresos_total' => round((float) $egresos->sum('monto'), 2),
                'pagos_empleados' => round((float) $pagos->sum('monto'), 2),
                // Lo que en conjunto se descuenta del efectivo esperado en caja.
                'salida_efectivo' => round(
                    (float) $egresos->where('tipo_pago', 'efectivo')->sum('monto') + (float) $pagos->sum('monto'),
                    2
                ),
            ],
        ]);
    }

    public function empleados()
    {
        $empleados = Empleado::query()
            ->where('sucursal_id', Auth::user()->sucursal_id)
            ->where('estatus', 'activo')
            ->orderBy('nombres')
            ->get();

        return response()->json([
            'empleados' => $empleados->map(fn (Empleado $empleado) => [
                'id' => $empleado->id,
                'nombre' => trim($empleado->nombres . ' ' . $empleado->apellidos),
                'puesto' => $empleado->puesto ?? null,
            ])->values(),
        ]);
    }

    public function registrarEgreso(Request $request)
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'descripcion' => ['required', 'string', 'min:3', 'max:255'],
            'tipo_pago' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'imagen' => ['nullable', 'image', 'max:5120'],
        ], [
            'monto.required' => 'Indica el monto del egreso.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'descripcion.required' => 'Describe en qué se gastó.',
            'descripcion.min' => 'La descripción es demasiado corta.',
            'imagen.image' => 'El comprobante debe ser una imagen.',
            'imagen.max' => 'El comprobante no puede pesar más de 5 MB.',
        ]);

        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        $egreso = Egreso::create([
            'user_id' => Auth::id(),
            'apertura_id' => $apertura->id,
            'monto' => $datos['monto'],
            'descripcion' => $datos['descripcion'],
            'tipo_pago' => $datos['tipo_pago'],
            'estatus' => 'activo',
            'referencia' => $datos['referencia'] ?? 'N/A',
            // El campo del formulario tiene que llamarse 'imagen': el mutator del
            // modelo delega en uploadFileToDisk de Backpack, que no usa el valor
            // recibido sino que lee request()->file('imagen') por su cuenta.
            'imagen' => $request->file('imagen'),
            'fecha_pago' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Egreso registrado.',
            'egreso' => [
                'id' => $egreso->id,
                'monto' => (float) $egreso->monto,
                'descripcion' => $egreso->descripcion,
                'tipo_pago' => $egreso->tipo_pago,
                'fecha' => (string) $egreso->fecha_pago,
                'comprobante' => $this->urlComprobante($egreso->imagen),
            ],
        ], 201);
    }

    /**
     * El comprobante es obligatorio: el panel de administración tiene un reporte
     * que revisa justamente esas imágenes para autorizar la nómina.
     */
    public function registrarPagoEmpleado(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:empleados,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'imagen' => ['required', 'image', 'max:5120'],
        ], [
            'empleado_id.required' => 'Selecciona al empleado.',
            'monto.required' => 'Indica el monto del pago.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'imagen.required' => 'El comprobante del pago es obligatorio.',
            'imagen.image' => 'El comprobante debe ser una imagen.',
            'imagen.max' => 'El comprobante no puede pesar más de 5 MB.',
        ]);

        $user = Auth::user();
        $apertura = Apertura::aperturaActiva($user);

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        $empleado = Empleado::query()
            ->where('id', $datos['empleado_id'])
            ->where('sucursal_id', $user->sucursal_id)
            ->first();

        if (! $empleado) {
            return response()->json([
                'message' => 'Ese empleado no pertenece a tu sucursal.',
            ], 422);
        }

        $pago = EmpleadoPago::create([
            'user_id' => $user->id,
            'apertura_id' => $apertura->id,
            'empleado_id' => $empleado->id,
            'monto' => $datos['monto'],
            'estatus' => 'activo',
            'fecha_pago' => Carbon::now(),
            // Igual que en el egreso: el mutator del modelo hace la subida y lee
            // el archivo directamente de request()->file('imagen').
            'imagen' => $request->file('imagen'),
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Pago registrado.',
            'pago' => [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'empleado' => trim($empleado->nombres . ' ' . $empleado->apellidos),
                'fecha' => (string) $pago->fecha_pago,
                'comprobante' => $this->urlComprobante($pago->imagen),
            ],
        ], 201);
    }

    /**
     * El disco 'pagos' declara url => APP_URL.'/storage', que omite el segmento
     * 'pagos' de su propia raíz, así que Storage::url() devuelve una ruta rota.
     * El resto del proyecto arma la URL a mano; aquí se hace igual.
     */
    private function urlComprobante(?string $ruta): ?string
    {
        return $ruta ? asset('storage/pagos/' . $ruta) : null;
    }
}
