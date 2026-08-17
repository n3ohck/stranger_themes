<?php

namespace App\Actions;

use App\Jobs\ComprobanteDigitalJob;
use App\Models\Descuento;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use App\Traits\DateTrait;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaAction
{
    use DateTrait;

    public $user;

    public function __construct()
    {
        $this->user = backpack_user();
    }

    /**
     * Asigna el folio de la sucursal a la que pertenece la venta.
     *
     * Cada sucursal lleva su propio consecutivo con prefijo (PV-1, PV-2, ...),
     * repartido por Sucursal::tomarFolio() con bloqueo de fila.
     */
    public static function sellarFolio(Venta $venta): Venta
    {
        $folio = Sucursal::tomarFolio($venta->sucursal_id);

        $venta->folio = $folio['folio'];
        $venta->folio_consecutivo = $folio['consecutivo'];
        $venta->save();

        return $venta;
    }

    /**
     * La columna folio es NOT NULL, así que se siembra en el insert y se sella
     * con el consecutivo real inmediatamente después, en la misma transacción.
     */
    public const FOLIO_PENDIENTE = 'PENDIENTE';

    public function do(array $ventas): array
    {
        $nuevasVentas = [];
        foreach ($ventas as $venta) {
            $venta['datetime'] = $this->makeDate($venta['datetime'])->setTimezone( config('app.display_timezone', 'America/Chihuahua') )->format('Y-m-d H:i:s');
            $existe = Venta::query()
                ->where('created_at', $venta['datetime'])
                ->where('total', $venta['total'])
                ->exists();
            if ($existe) continue;
            $nuevaVenta = Venta::create([
                'user_id' => $this->user->id,
                'descuento_id' => $venta['descuento_id'] ?? null,
                'sucursal_id' => $this->user->sucursal_id,
                'origen' => 'pos',
                'folio' => self::FOLIO_PENDIENTE,
                'total' => $venta['total'],
                'codigo_descuento' => $venta['codigo_descuento'] ?? null,
                'descuento' => $venta['descuento'] ?? null,
                'porcentaje_descuento' => $venta['porcentaje_descuento'] ?? null,
                'created_at' => $venta['datetime']
            ]);
            self::sellarFolio($nuevaVenta);
            $this->makeVentaProductos($nuevaVenta->id, $venta['productos']);
            $this->makeVentaPagos($nuevaVenta->id, $venta['pagos']);

            if (isset($venta['reservaciones'])) {
                $reservaciones = $this->makeVentaReservacion($nuevaVenta->id, $venta['reservaciones']);
            }

            $nuevasVentas[] = [
                'venta_id' => $nuevaVenta->id,
                'estatus' => $nuevaVenta->estatus,
                'folio' => $nuevaVenta->folio,
                'total' => $nuevaVenta->total,
                'reservaciones' => $reservaciones ?? []
            ];
        }
        return $nuevasVentas;
    }

    /**
     * @throws \Exception
     */
    /**
     * @throws \Exception
     */
    public function saleOnline(array $sales): array
    {
        $newSales = [];

        foreach ($sales as $sale) {
            $payments = collect($sale['pagos'] ?? [])
                ->where('tipo', 'online')
                ->values();

            if ($payments->isEmpty()) {
                throw new \Exception('No se han encontrado pagos ONLINE para la venta', 400);
            }

            $onlineRefs = $payments
                ->pluck('referencia')
                ->filter(fn ($r) => !is_null($r) && trim((string) $r) !== '')
                ->map(fn ($r) => trim((string) $r))
                ->unique()
                ->values();

            if ($onlineRefs->isEmpty()) {
                throw new \Exception('La venta ONLINE no incluye referencia de pago', 422);
            }

            // Una referencia de Stripe corresponde a un cobro. Si ya hay una venta
            // con ella, este request es un reintento y no una compra nueva.
            $referenciaPago = $onlineRefs->first();

            $yaRegistrada = Venta::query()
                ->withoutGlobalScopes()
                ->where('referencia_pago', $referenciaPago)
                ->exists();

            if ($yaRegistrada) {
                Log::warning('Venta online duplicada ignorada por referencia', [
                    'referencias' => $onlineRefs->all(),
                    'email' => $sale['email'] ?? null,
                ]);

                continue;
            }

            $sucursalId = $this->resolverSucursal($sale);

            $booking = collect($sale['reservaciones'] ?? []);

            $createdAt = null;

            if ($booking->isNotEmpty() && isset($booking->first()['datetime'])) {
                $createdAt = $this->makeDate($booking->first()['datetime']);
            } elseif (isset($sale['datetime'])) {
                $createdAt = $this->makeDate($sale['datetime']);
            } else {
                $createdAt = now();
            }

            /**
             * Total real de la venta.
             * Si viene total en el payload, lo respetamos.
             * Si no viene, sumamos productos.
             */
            $totalVenta = isset($sale['total'])
                ? (float) $sale['total']
                : collect($sale['productos'] ?? [])->sum(fn ($p) => (float) ($p['total'] ?? 0));

            try {
                $venta = Venta::create([
                    'user_id' => 1,
                    'descuento_id' => $sale['descuento_id'] ?? null,
                    'sucursal_id' => $sucursalId,
                    'origen' => 'web',
                    'referencia_pago' => $referenciaPago,
                    'folio' => self::FOLIO_PENDIENTE,
                    'total' => $totalVenta,
                    'codigo_descuento' => $sale['codigo_descuento'] ?? null,
                    'descuento' => $sale['descuento'] ?? null,
                    'porcentaje_descuento' => $sale['porcentaje_descuento'] ?? null,
                    'created_at' => $createdAt,
                    'nombre' => $sale['nombre'] ?? null,
                    'telefono' => $sale['telefono'] ?? null,
                    'email' => $sale['email'] ?? null,
                ]);
            } catch (QueryException $e) {
                // El índice único de referencia_pago es la garantía real: si dos
                // peticiones del sitio llegan a la vez, ambas pasan la consulta
                // previa y es la base la que rechaza a la segunda. Se trata como
                // reintento, no como error, para no devolverle una falla al sitio
                // por una compra que sí quedó registrada.
                if ($this->esReferenciaDuplicada($e)) {
                    Log::warning('Venta online duplicada bloqueada por la base de datos', [
                        'referencia' => $referenciaPago,
                        'email' => $sale['email'] ?? null,
                    ]);

                    continue;
                }

                throw $e;
            }

            self::sellarFolio($venta);
            $this->makeVentaProductosOnline($venta->id, $sale['productos'] ?? []);
            $this->makeVentaPagos($venta->id, $sale['pagos'] ?? []);

            $reservations = [];

            if (!empty($sale['reservaciones'])) {
                $reservations = $this->makeVentaReservacion(
                    $venta->id,
                    $sale['reservaciones'],
                    $sucursalId
                );
            }

            if (!empty($sale['email'])) {
                ComprobanteDigitalJob::dispatch($venta->id);
            }

            $newSales[] = [
                'venta_id' => $venta->id,
                'estatus' => $venta->estatus,
                'folio' => $venta->folio,
                'total' => $venta->total,
                'referencias' => $onlineRefs->all(),
                'reservaciones' => $reservations,
            ];
        }

        return $newSales;
    }

    /**
     * La sucursal de una venta web llega en el payload del sitio. Se valida que
     * exista: con dos sucursales operando, un id equivocado ya no es inofensivo,
     * mandaría la venta y su reservación a la sucursal que no es.
     */
    private function resolverSucursal(array $sale): int
    {
        $sucursalId = (int) ($sale['sucursal_id'] ?? 0);

        if (! $sucursalId || ! Sucursal::query()->whereKey($sucursalId)->exists()) {
            throw new \Exception('La venta no indica una sucursal válida', 422);
        }

        return $sucursalId;
    }

    private function esReferenciaDuplicada(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'ventas_referencia_pago_unique');
    }

    /**
     * La reservación siempre pertenece a la sucursal de su venta.
     *
     * Cuando la llamada trae $branchId es una venta web y no hay usuario en
     * sesión; sin él es una venta de mostrador y la sucursal es la del cajero.
     * Antes se leía $this->user->sucursal_id primero, lo que en las ventas web
     * accedía a una propiedad sobre null y solo funcionaba porque PHP devuelve
     * null con un aviso.
     */
    public function makeVentaReservacion(int $ventaId, array $reservas, $branchId = null): array
    {
        $sucursalId = $branchId ?? optional($this->user)->sucursal_id;

        $reservasNuevas = [];
        foreach ($reservas as $reserva) {
            $reserva['datetime'] = $this->makeDate($reserva['datetime']);
            $reservasNuevas[] = Reserva::create([
                'producto_id' => $reserva['producto_id'],
                'nombre_cliente' => $reserva['name'],
                'cantidad_personas' => $reserva['number'],
                'fecha' => $reserva['datetime'],
                'estado' => 'confirmada',
                'sucursal_id' => $sucursalId,
                'venta_id' => $ventaId
            ]);
        }
        return $reservasNuevas;
    }

    public function makeVentaProductos(int $ventaId, array $productos): void
    {
        $totalDescuento = 0;
        $subtotal = 0;
        $descuentoDinero = 0;
        $porcentaje = 0;
        $descuentos = [];
        $descuentoId = null;
        $codigoDescuento = null;
        foreach ($productos as $producto) {
            // precio es unitario: así lo guardan las líneas y así lo leen los
            // reportes (precio * cantidad). Antes aquí se tomaba como total de
            // línea, lo que descuadraba la cabecera en cuanto cantidad > 1.
            $subtotalLinea = $producto['precio'] * $producto['cantidad'];
            $subtotal += $subtotalLinea;

            if (isset($producto['descuentos'])) {
                foreach ($producto['descuentos'] as $descuento) {
                    $totalDescuento += $subtotalLinea - $producto['total'];
                    $descuentoDinero = $subtotalLinea - $producto['total'];
                    $porcentaje = $descuento['porcentaje_descuento'] ?? 0;
                    $descuentoId = $descuento['descuento_id'] ?? null;
                    $codigoDescuento = $descuentos['codigo_descuento'] ?? null;
                }
            }

            VentaProducto::create([
                'venta_id' => $ventaId,
                'producto_id' => $producto['producto_id'],
                'precio' => $producto['precio'],
                'cantidad' => $producto['cantidad'],
                'total' => $producto['total'],
                'descuento_id' => $descuentoId,
                'codigo_descuento' => $codigoDescuento,
                'descuento' => number_format($descuentoDinero, 2, '.', ''),
                'porcentaje_descuento' => $porcentaje
            ]);
            (new ExistenciaAction())::salidarPorVenta($producto['producto_id'], $producto['cantidad']);
        }
        $venta = Venta::find($ventaId);
        $venta->update([
            'descuento' => $totalDescuento,
            'porcentaje_descuento' => ($subtotal) ? number_format(($totalDescuento / $subtotal) * 100, 2, '.', '') : 0
        ]);
    }

    public function makeVentaProductosOnline(int $ventaId, array $productos): void
    {
        $totalDescuento = 0;
        $subtotal = 0;
        foreach ($productos as $producto) {
            $subtotal += $producto['precio'] * $producto['cantidad'];
            $totalDescuento+=  $producto['descuento'] ?? 0;
            $codigoDescuento = null;
            if (isset($producto['descuento_id'])) {
                $codigoDescuento = Descuento::query()
                    ->where('id', $producto['descuento_id'])
                    ->value('codigo');
            }

            VentaProducto::create([
                'venta_id' => $ventaId,
                'producto_id' => $producto['producto_id'],
                'precio' => $producto['precio'],
                'cantidad' => $producto['cantidad'],
                'total' => $producto['total'],
                'descuento_id' => $producto['descuento_id'] ?? null,
                'codigo_descuento' => $codigoDescuento ?? null,
                'descuento' => number_format( $producto['descuento'] ?? 0, 2, '.', ''),
                'porcentaje_descuento' => $producto['porcentaje_descuento'] ?? 0
            ]);
            (new ExistenciaAction())::salidarPorVenta($producto['producto_id'], $producto['cantidad']);
        }
        $venta = Venta::find($ventaId);
        $venta->update([
            'descuento' => $totalDescuento,
            'porcentaje_descuento' => ($subtotal) ? number_format(($totalDescuento / $subtotal) * 100, 2, '.', '') : 0
        ]);
    }

    public function makeVentaPagos(int $ventaId, array $pagos): void
    {
        foreach ($pagos as $pago) {
            $tipo = $pago['tipo'] ?? null;
            $referencia = isset($pago['referencia'])
                ? trim((string) $pago['referencia'])
                : null;

            if ($tipo === 'online' && $referencia) {
                $exists = VentaPago::query()
                    ->where('tipo', 'online')
                    ->where('referencia', $referencia)
                    ->exists();

                if ($exists) {
                    Log::warning('Pago online duplicado ignorado', [
                        'venta_id' => $ventaId,
                        'referencia' => $referencia,
                    ]);

                    continue;
                }
            }

            VentaPago::create([
                'venta_id' => $ventaId,
                'monto' => $pago['monto'],
                'cambio' => $pago['cambio'] ?? 0,
                'tipo' => $tipo,
                'referencia' => $referencia,
            ]);
        }
    }

    public
    function cancelVentas(array $ventas): array
    {
        $ventasCanceladas = [];
        foreach ($ventas as $venta) {
            $ventaActualizar = Venta::find($venta['venta_id']);
            if (!$ventaActualizar) throw new \Exception('No se ha encontrado la venta a cancelar');
            if ($ventaActualizar->estatus !== 'cancelado') {
                $ventaActualizar->update([
                    'estatus' => 'cancelado',
                    'user_id_cancelacion' => backpack_user()->id,
                    'fecha_cancelacion' => Carbon::now()->setTimezone( config('app.display_timezone', 'America/Chihuahua') )->format('Y-m-d H:i:s'),
                    'comentario_cancelacion' => $venta['comentario_cancelacion'] ?? 'N/A'
                ]);
                $ventaActualizar->reservaciones()->update(['estado' => 'cancelada']);
                $ventaActualizar->save();
                foreach ($ventaActualizar->productos as $producto) {
                    (new ExistenciaAction())::existenciaCancelacion($producto->producto_id, $producto->cantidad);
                }
                $ventasCanceladas[] = [
                    'venta_id' => $ventaActualizar->id,
                    'folio' => $ventaActualizar->folio,
                    'estatus' => $ventaActualizar->estatus,
                    'reservaciones' => $ventaActualizar->reservaciones->toArray()
                ];
            }

        }
        return $ventasCanceladas;
    }
}
