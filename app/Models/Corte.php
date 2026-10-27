<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use App\Support\Caja\ResumenCorte;
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

    /** @var array|null */
    private $salidasCache = null;
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
        'fondo_final',
        'total_online'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'total_online' => 'float',
        'total' => 'float',
        'efectivo' => 'float',
        'tarjeta' => 'float',
        'transferencia' => 'float',
        'total_caja' => 'float',
        'fondo_final' => 'float'
    ];

    protected $appends = ['total_egresos_efectivo', 'efectivo_egreso', 'total_egresos','efectivo_fondo','pago_empleados','ganancia'];

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
    /**
     * Egresos y pagos a empleados del turno, con la misma regla que el precorte
     * del POS y el ticket impreso. Se calcula una vez por instancia: el listado
     * del panel lee varias columnas que dependen de esto.
     */
    public function salidas(): array
    {
        if ($this->salidasCache === null) {
            $apertura = $this->apertura_id
                ? Apertura::query()->withoutGlobalScopes()->find($this->apertura_id)
                : null;

            $this->salidasCache = ResumenCorte::salidas(
                $this->apertura_id ? (int) $this->apertura_id : null,
                (int) $this->attributes['sucursal_id'],
                Carbon::parse($apertura->created_at ?? $this->attributes['fecha_inicio']),
                Carbon::parse($this->attributes['fecha_final'] ?? Carbon::now())
            );
        }

        return $this->salidasCache;
    }

    // Todo egreso del turno es en efectivo; se conserva el nombre por compatibilidad.
    public function getTotalEgresosEfectivoAttribute()
    {
        return $this->salidas()['egresos'];
    }

    public function getTotalEgresosAttribute()
    {
        return $this->salidas()['egresos'];
    }

    public function getPagoEmpleadosAttribute()
    {
        return $this->salidas()['pagos_empleados'];
    }

    public function getEfectivoFondoAttribute()
    {
        return (float) optional($this->apertura)->monto_apertura + $this->attributes['efectivo'];
    }

    public function getEfectivoEgresoAttribute()
    {
        return number_format(($this->attributes['efectivo'] - $this->total_egresos), 2, '.', '');
    }

    /** Igual que en el ticket: todo lo que entró menos egresos y pagos a empleados. */
    public function getGananciaAttribute()
    {
        $ingresos = (float) $this->attributes['efectivo'] + (float) $this->attributes['tarjeta']
            + (float) ($this->attributes['transferencia'] ?? 0) + (float) ($this->attributes['total_online'] ?? 0);

        return number_format(ResumenCorte::ganancia($ingresos, $this->salidas()), 2, '.', '');
    }
}
