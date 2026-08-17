<?php

namespace App\Actions\Pos;

use App\Actions\ExistenciaAction;
use App\Actions\VentaAction;
use App\Models\Apertura;
use App\Models\Descuento;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaProducto;
use App\Support\ReglaDescuento;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Registra una venta de mostrador.
 *
 * Diferencia principal con VentaAction::do(): aquí el servidor es la autoridad
 * sobre los precios. El POS manda qué se vendió y con qué código de descuento;
 * los importes se calculan a partir del catálogo. En el flujo anterior el
 * navegador enviaba precio, descuento y total ya calculados, así que cualquiera
 * con la consola abierta podía fijar el precio de una venta.
 */
class RegistrarVentaAction
{
    private $user;

    private $sucursalId;

    public function __construct($user)
    {
        $this->user = $user;
        $this->sucursalId = $user->sucursal_id;
    }

    /**
     * @param  array  $datos  Estructura ya validada por RegistrarVentaRequest.
     */
    public function ejecutar(array $datos, Apertura $apertura): Venta
    {
        $lineas = $this->resolverLineas($datos['items']);
        $totalNeto = $lineas->sum('total');
        $totalDescuento = $lineas->sum('descuento');
        $subtotal = $lineas->sum('subtotal');

        $this->validarPagos($datos['pagos'] ?? [], $totalNeto);

        $venta = Venta::create([
            'user_id' => $this->user->id,
            'sucursal_id' => $this->sucursalId,
            'apertura_id' => $apertura->id,
            'origen' => 'pos',
            'folio' => VentaAction::FOLIO_PENDIENTE,
            'total' => round($totalNeto, 2),
            'descuento' => round($totalDescuento, 2),
            'porcentaje_descuento' => $subtotal > 0 ? round(($totalDescuento / $subtotal) * 100, 2) : 0,
            'descuento_id' => $lineas->pluck('descuento_id')->filter()->first(),
            'codigo_descuento' => $lineas->pluck('codigo_descuento')->filter()->unique()->implode(', ') ?: null,
            'nombre' => $datos['cliente']['nombre'] ?? null,
            'telefono' => $datos['cliente']['telefono'] ?? null,
            'email' => $datos['cliente']['email'] ?? null,
            'created_at' => Carbon::now(),
        ]);

        VentaAction::sellarFolio($venta);

        foreach ($lineas as $linea) {
            $this->crearLinea($venta, $linea);
        }

        $this->crearPagos($venta, $datos['pagos'], $totalNeto);

        return $venta->fresh(['productos.producto', 'pagos', 'reservaciones.producto', 'sucursal', 'user']);
    }

    /**
     * Convierte los items del POS en líneas con importes calculados aquí.
     */
    private function resolverLineas(array $items): Collection
    {
        $productos = Producto::query()
            ->whereIn('id', collect($items)->pluck('producto_id')->unique())
            ->get()
            ->keyBy('id');

        return collect($items)->map(function (array $item) use ($productos) {
            $producto = $productos->get($item['producto_id']);

            if (! $producto) {
                throw ValidationException::withMessages([
                    'items' => "El producto {$item['producto_id']} no existe o no pertenece a tu sucursal.",
                ]);
            }

            $cantidad = (float) $item['cantidad'];
            $precio = (float) $producto->precio;
            $subtotal = $precio * $cantidad;

            $descuento = $this->resolverDescuento($item['codigo_descuento'] ?? null, $producto);
            $descuentoUnitario = ReglaDescuento::unitario($precio, $descuento['porcentaje']);
            $totalLinea = max(0.0, $subtotal - ($descuentoUnitario * $cantidad));

            return [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal,
                'descuento' => $descuentoUnitario * $cantidad,
                'descuento_unitario' => $descuentoUnitario,
                'porcentaje_descuento' => $descuento['porcentaje'],
                'descuento_id' => $descuento['id'],
                'codigo_descuento' => $descuento['codigo'],
                'total' => $totalLinea,
                'reservaciones' => $item['reservaciones'] ?? [],
                'cliente' => $item['cliente'] ?? null,
            ];
        });
    }

    /**
     * Un código solo aplica si está activo, es de la sucursal y su producto_tipo
     * corresponde al del producto. producto_tipo vacío significa "cualquiera".
     */
    private function resolverDescuento(?string $codigo, Producto $producto): array
    {
        $vacio = ['id' => null, 'codigo' => null, 'porcentaje' => 0.0];

        if (! $codigo) {
            return $vacio;
        }

        $descuento = Descuento::query()
            ->where('codigo', $codigo)
            ->where('estatus', 'activo')
            ->first();

        if (! $descuento) {
            throw ValidationException::withMessages([
                'items' => "El código de descuento {$codigo} no existe o no está activo.",
            ]);
        }

        if ($descuento->producto_tipo && $descuento->producto_tipo !== $producto->tipo) {
            throw ValidationException::withMessages([
                'items' => "El código {$codigo} no aplica para {$producto->descripcion}.",
            ]);
        }

        return [
            'id' => $descuento->id,
            'codigo' => $descuento->codigo,
            'porcentaje' => (float) $descuento->porcentaje,
        ];
    }

    private function crearLinea(Venta $venta, array $linea): void
    {
        $producto = $linea['producto'];

        VentaProducto::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'precio' => $linea['precio'],
            'cantidad' => $linea['cantidad'],
            'total' => round($linea['total'], 2),
            'descuento_id' => $linea['descuento_id'],
            'codigo_descuento' => $linea['codigo_descuento'],
            'descuento' => round($linea['descuento'], 2),
            'porcentaje_descuento' => $linea['porcentaje_descuento'],
        ]);

        ExistenciaAction::salidarPorVenta($producto->id, (int) $linea['cantidad']);

        foreach ($linea['reservaciones'] as $reservacion) {
            Reserva::create([
                'producto_id' => $reservacion['producto_id'] ?? $producto->id,
                'nombre_cliente' => $reservacion['nombre'] ?? $linea['cliente'] ?? $venta->nombre ?? 'Mostrador',
                'cantidad_personas' => $reservacion['personas'] ?? $linea['cantidad'],
                'fecha' => Carbon::parse($reservacion['fecha'] . ' ' . $reservacion['hora']),
                'estado' => 'confirmada',
                'sucursal_id' => $this->sucursalId,
                'venta_id' => $venta->id,
            ]);
        }
    }

    /**
     * El cambio se calcula aquí y se guarda solo en el pago en efectivo, que es
     * el único con el que se devuelve dinero.
     */
    private function crearPagos(Venta $venta, array $pagos, float $totalNeto): void
    {
        $recibidoEfectivo = collect($pagos)->where('tipo', 'efectivo')->sum('monto');
        $otrosPagos = collect($pagos)->where('tipo', '!=', 'efectivo')->sum('monto');
        $cambio = max(0.0, ($recibidoEfectivo + $otrosPagos) - $totalNeto);

        foreach ($pagos as $pago) {
            $esEfectivo = $pago['tipo'] === 'efectivo';

            VentaPago::create([
                'venta_id' => $venta->id,
                'monto' => round((float) $pago['monto'], 2),
                'cambio' => $esEfectivo ? round($cambio, 2) : 0,
                'tipo' => $pago['tipo'],
                'referencia' => $pago['referencia'] ?? null,
            ]);

            // El cambio se registra una sola vez aunque haya varios pagos en efectivo.
            if ($esEfectivo) {
                $cambio = 0.0;
            }
        }
    }

    private function validarPagos(array $pagos, float $totalNeto): void
    {
        if (empty($pagos)) {
            throw ValidationException::withMessages([
                'pagos' => 'La venta debe incluir al menos una forma de pago.',
            ]);
        }

        $recibido = collect($pagos)->sum('monto');

        if (round($recibido, 2) + 0.001 < round($totalNeto, 2)) {
            throw ValidationException::withMessages([
                'pagos' => sprintf(
                    'El pago recibido ($%s) es menor al total de la venta ($%s).',
                    number_format($recibido, 2),
                    number_format($totalNeto, 2)
                ),
            ]);
        }

        $conCambio = collect($pagos)->where('tipo', '!=', 'efectivo')->sum('monto');

        if (round($conCambio, 2) > round($totalNeto, 2) + 0.001) {
            throw ValidationException::withMessages([
                'pagos' => 'Los pagos que no son en efectivo no pueden exceder el total de la venta.',
            ]);
        }
    }
}
