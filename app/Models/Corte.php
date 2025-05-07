<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Corte extends Model
{
    use CrudTrait, RevisionableTrait, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    public $search;
    protected $table = 'cortes';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'total',
        'efectivo',
        'tarjeta',
        'transferencia',
        'total_caja',
        'fecha_inicio',
        'fecha_final',
        'user_id',
        'sucursal_id',
        'apertura_id',
        'fondo_final'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'total' => 'float',
        'efectivo' => 'float',
        'tarjeta' => 'float',
        'transferencia' => 'float',
        'total_caja' => 'float',
        'fondo_final' => 'float'
    ];

    protected $appends = ['total_egresos_efectivo', 'total_online', 'efectivo_egreso', 'total_egresos','efectivo_fondo','pago_empleados','ganancia'];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::addGlobalScope(new SucursalFilterScope);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apertura(): BelongsTo
    {
        return $this->belongsTo(Apertura::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeSearch($query, $search)
    {
        $this->search = $search;
        return $query
            ->when($search->fecha_inicio, function ($q, $fecha_inicio) {
                $q->where('fecha_inicio', '>=', $fecha_inicio);
            })
            ->when($search->fecha_final, function ($q, $fecha_final) {
                $q->where('fecha_final', '<=', $fecha_final);
            })
            ->when($search->user_id, function ($q, $user_id) {
                $q->where('user_id', $user_id);
            })
            ->when($search->apertura_id, function ($q, $apertura_id) {
                $q->where('apertura_id', $apertura_id);
            });
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function getTotalEgresosEfectivoAttribute()
    {
        $fechaInicio = Carbon::parse($this->attributes['fecha_inicio'])->startOfDay();
        $fechaFinal = Carbon::parse($this->attributes['fecha_final'])->endOfDay();
        return Egreso::query()
            ->select(['monto','fecha_pago','estatus','sucursal_id'])
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFinal])
            ->where('estatus','activo')
            ->where('sucursal_id', $this->attributes['sucursal_id'])
            ->where('tipo_pago','efectivo')
            ->sum('monto');
    }

    public function getTotalEgresosAttribute()
    {
        $fechaInicio = Carbon::parse($this->attributes['fecha_inicio'])->startOfDay();
        $fechaFinal = Carbon::parse($this->attributes['fecha_final'])->endOfDay();
        return Egreso::query()
            ->select(['monto','fecha_pago','estatus','sucursal_id'])
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFinal])
            ->where('estatus','activo')
            ->where('sucursal_id', $this->attributes['sucursal_id'])
            ->sum('monto') ?? 0;
    }

    public function getPagoEmpleadosAttribute()
    {
        $fechaInicio = Carbon::parse($this->apertura->created_at)->startOfDay();
        $fechaFinal = Carbon::parse($this->attributes['fecha_final'])->endOfDay();
        return EmpleadoPago::query()
            ->select(['monto','fecha_pago','estatus'])
            ->whereBetween('created_at', [$fechaInicio, $fechaFinal])
            ->where('estatus','activo')
            ->sum('monto') ?? 0;
    }

    public function getEfectivoFondoAttribute()
    {
        return $this->apertura->monto_apertura + $this->attributes['efectivo'];
    }

    public function getEfectivoEgresoAttribute()
    {
        return number_format(($this->attributes['efectivo'] - $this->total_egresos), 2, '.', '');
    }

    public function getGananciaAttribute()
    {
        $totalEgresos =  ($this->pago_empleados + $this->total_egresos);
        return number_format(( ($this->attributes['total'] + $this->total_online) - $totalEgresos), 2, '.', '');
    }

    public function getTotalOnlineAttribute()
    {
        return VentaPago::query()->where('tipo','online')
            ->whereHas('venta',function($q){
                $q->where('sucursal_id', $this->attributes['sucursal_id']);
            })
            ->whereBetween('created_at', [$this->attributes['fecha_inicio'], $this->attributes['fecha_final']])
            ->sum('monto') ?? 0;
    }
}
