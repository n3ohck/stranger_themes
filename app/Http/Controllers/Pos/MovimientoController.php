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
                'fecha' => (string) $egreso->fecha_pago,
                'comprobante' => $this->urlComprobante($egreso->imagen),
            ])->values(),

            'pagos' => $pagos->map(fn (EmpleadoPago $pago) => [
                'id' => $pago->id,
                'empleado_id' => $pago->empleado_id,
                'monto' => (float) $pago->monto,
                'empleado' => trim(optional($pago->empleado)->nombres . ' ' . optional($pago->empleado)->apellidos),
                'fecha' => (string) $pago->fecha_pago,
                'comprobante' => $this->urlComprobante($pago->imagen),
            ])->values(),

            // Tres cifras y no cinco: como todo egreso es en efectivo, distinguir
            // "egresos efectivo" de "egresos total" solo repetía el mismo número.
            'totales' => [
                'egresos' => round((float) $egresos->sum('monto'), 2),
                'pagos_empleados' => round((float) $pagos->sum('monto'), 2),
                'salida_efectivo' => round((float) $egresos->sum('monto') + (float) $pagos->sum('monto'), 2),
                'pagos_sin_firma' => $pagos->whereNull('imagen')->count(),
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
            // Solo efectivo: un egreso es dinero saliendo del cajón. Lo que se paga
            // con tarjeta o transferencia de la empresa no toca la caja del turno y
            // se registra desde el panel, no desde el punto de venta.
            'tipo_pago' => ['nullable', 'in:efectivo'],
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
            'tipo_pago' => 'efectivo',
            'estatus' => 'activo',
            'referencia' => 'N/A',
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
     * Se registra sin comprobante a propósito. El comprobante es la foto del
     * ticket firmado por el empleado, y ese ticket solo existe después de
     * registrar el pago: se imprime con el folio y se sube con
     * subirComprobantePago(). Mientras falte, el pago queda pendiente de firma.
     */
    public function registrarPagoEmpleado(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:empleados,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ], [
            'empleado_id.required' => 'Selecciona al empleado.',
            'monto.required' => 'Indica el monto del pago.',
            'monto.min' => 'El monto debe ser mayor a cero.',
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
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Pago registrado.',
            'pago' => [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'empleado' => trim($empleado->nombres . ' ' . $empleado->apellidos),
                'fecha' => (string) $pago->fecha_pago,
                'comprobante' => null,
            ],
        ], 201);
    }

    /**
     * Foto del ticket ya firmado. Se puede reemplazar mientras el turno siga
     * abierto, por si la foto salió movida; el mutator borra la anterior.
     */
    public function subirComprobantePago(Request $request, int $pago)
    {
        $request->validate([
            'imagen' => ['required', 'image', 'max:5120'],
        ], [
            'imagen.required' => 'Sube la foto del ticket firmado.',
            'imagen.image' => 'El comprobante debe ser una imagen.',
            'imagen.max' => 'El comprobante no puede pesar más de 5 MB.',
        ]);

        $apertura = Apertura::aperturaActiva(Auth::user());

        if (! $apertura) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 409);
        }

        // Solo pagos del turno en curso: uno de otro turno ya quedó en su corte.
        $pago = EmpleadoPago::query()
            ->where('id', $pago)
            ->where('apertura_id', $apertura->id)
            ->where('estatus', 'activo')
            ->with('empleado')
            ->first();

        if (! $pago) {
            return response()->json(['message' => 'Ese pago no es de tu turno.'], 404);
        }

        // Igual que en el egreso: el mutator del modelo hace la subida y lee
        // el archivo directamente de request()->file('imagen').
        $pago->imagen = $request->file('imagen');
        $pago->save();

        return response()->json([
            'message' => 'Ticket firmado guardado.',
            'pago' => [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'empleado' => trim(optional($pago->empleado)->nombres . ' ' . optional($pago->empleado)->apellidos),
                'fecha' => (string) $pago->fecha_pago,
                'comprobante' => $this->urlComprobante($pago->imagen),
            ],
        ]);
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
