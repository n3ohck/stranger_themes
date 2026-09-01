<?php

namespace App\Support\Tienda;

use App\Models\Descuento;
use App\Models\Producto;
use App\Support\ReglaDescuento;
use Illuminate\Validation\ValidationException;

/**
 * Resuelve un código de descuento contra un producto y calcula los importes.
 *
 * Es la única fuente de verdad de lo que cuesta una compra con código: la usan tanto
 * el endpoint que valida mientras el cliente escribe como el que aparta la compra
 * antes de cobrar, para que lo que se muestra y lo que se cobra no puedan diferir.
 */
class DescuentoAplicado
{
    public ?int $id = null;
    public ?string $codigo = null;
    public float $porcentaje = 0.0;
    public float $unitario = 0.0;

    public float $precioUnitario;
    public int $personas;

    private function __construct(float $precioUnitario, int $personas)
    {
        $this->precioUnitario = $precioUnitario;
        $this->personas = $personas;
    }

    public static function sinCodigo(Producto $producto, int $personas): self
    {
        return new self((float) $producto->precio, $personas);
    }

    /**
     * @throws ValidationException si el código no aplica; el mensaje es el que ve el cliente.
     */
    public static function resolver(?string $codigo, Producto $producto, int $personas): self
    {
        $aplicado = self::sinCodigo($producto, $personas);

        $codigo = $codigo ? trim($codigo) : null;

        if (! $codigo) {
            return $aplicado;
        }

        $descuento = Descuento::query()
            ->withoutGlobalScopes()
            ->where('codigo', $codigo)
            ->where('sucursal_id', $producto->sucursal_id)
            ->where('estatus', 'activo')
            ->first();

        if (! $descuento) {
            throw ValidationException::withMessages([
                'codigo_descuento' => 'Ese código no existe o ya no está vigente.',
            ]);
        }

        if ($descuento->producto_tipo && $descuento->producto_tipo !== $producto->tipo) {
            throw ValidationException::withMessages([
                'codigo_descuento' => "El código {$descuento->codigo} no aplica para {$producto->descripcion}.",
            ]);
        }

        $unitario = ReglaDescuento::unitario((float) $producto->precio, (float) $descuento->porcentaje);

        // Un código que deja la compra en cero es de taquilla (cumpleañero, 2x1),
        // donde alguien verifica en persona que corresponde. En línea no hay quién
        // lo verifique, y además Stripe no admite cobros de cero.
        if ($unitario >= (float) $producto->precio) {
            throw ValidationException::withMessages([
                'codigo_descuento' => 'Ese código solo se puede usar en taquilla. Preséntalo al llegar.',
            ]);
        }

        $aplicado->id = $descuento->id;
        $aplicado->codigo = $descuento->codigo;
        $aplicado->porcentaje = (float) $descuento->porcentaje;
        $aplicado->unitario = $unitario;

        return $aplicado;
    }

    /** Precio por persona ya con el descuento aplicado. */
    public function precioConDescuento(): float
    {
        return round(max(0.0, $this->precioUnitario - $this->unitario), 2);
    }

    /** Lo que se cobra: precio con descuento por el número de participantes. */
    public function total(): float
    {
        return round($this->precioConDescuento() * $this->personas, 2);
    }

    /** Total sin descuento, para mostrar el ahorro. */
    public function subtotal(): float
    {
        return round($this->precioUnitario * $this->personas, 2);
    }

    public function descuentoTotal(): float
    {
        return round($this->unitario * $this->personas, 2);
    }

    public function aplica(): bool
    {
        return $this->id !== null && $this->unitario > 0;
    }

    public function paraJson(): array
    {
        return [
            'codigo' => $this->codigo,
            'porcentaje' => $this->porcentaje,
            'descuento_unitario' => round($this->unitario, 2),
            'precio_con_descuento' => $this->precioConDescuento(),
            'subtotal' => $this->subtotal(),
            'descuento' => $this->descuentoTotal(),
            'total' => $this->total(),
        ];
    }
}
