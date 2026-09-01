<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compra del sitio web que ya eligió horarios pero todavía no está pagada.
 *
 * Mientras está vigente bloquea sus horarios para los demás. Al confirmarse el pago
 * pasa a 'pagada' y queda ligada a la venta; si el cliente abandona, caduca sola y
 * los horarios vuelven a ofrecerse sin que nadie tenga que intervenir.
 *
 * No lleva el scope de sucursal: el sitio web opera sin sesión.
 */
class CompraPendiente extends Model
{
    protected $table = 'compras_pendientes';

    protected $fillable = [
        'referencia', 'sucursal_id', 'producto_id', 'personas', 'horarios',
        'nombre', 'email', 'telefono',
        'total', 'codigo_descuento', 'descuento_id', 'descuento', 'porcentaje_descuento',
        'stripe_session_id', 'stripe_payment_intent', 'estado', 'venta_id', 'expira_en',
    ];

    protected $casts = [
        'horarios' => 'array',
        'personas' => 'integer',
        'total' => 'float',
        'descuento' => 'float',
        'porcentaje_descuento' => 'float',
        'expira_en' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /** Apartados que todavía bloquean horarios. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', 'apartada')->where('expira_en', '>', Carbon::now());
    }

    public function expirada(): bool
    {
        return $this->estado === 'apartada' && $this->expira_en->isPast();
    }
}
