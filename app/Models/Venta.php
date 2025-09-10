<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Venta extends Model
{
    use CrudTrait, SoftDeletes, RevisionableTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    public $search;
    protected $table = 'ventas';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'user_id',
        'user_id_cancelacion',
        'descuento_id',
        'sucursal_id',
        'folio',
        'total',
        'codigo_descuento',
        'descuento',
        'porcentaje_descuento',
        'estatus',
        'fecha_cancelacion',
        'comentario_cancelacion',
        'nombre',
        'email',
        'telefono',
        'created_at'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'total' => 'float',
        'descuento' => 'float',
        'porcentaje_descuento' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCancelacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_cancelacion');
    }

    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(VentaProducto::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
    }

    public function reservaciones(): HasMany
    {
        return $this->hasMany(Reserva::class);
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
            ->when($this->search->folio, function ($query) {
                return $query->where('folio', $this->search->folio);
            })
            ->when($this->search->start_date, function ($query) {
                return $query->where('created_at', '>=', $this->search->start_date);
            })
            ->when($this->search->end_date, function ($query) {
                return $query->where('created_at', '<=', $this->search->end_date);
            })
            ->when($this->search->status, function ($query) {
                return $query->where('estatus', $this->search->status);
            })
            ->when($this->search->venta_id, function ($query) {
                return $query->where('id', $this->search->venta_id);
            })
            ->when($this->search->user_id, function ($query) {
                return $query->where('user_id', $this->search->user_id);
            });
    }

    public function scopeFilters($query, $search)
    {
        $search['dates'][0] = Carbon::parse($search['dates'][0])->startOfDay();
        $search['dates'][1] = Carbon::parse($search['dates'][1])->endOfDay();
        $query->when(isset($search['dates']), function ($query) use ($search) {
            $query->whereBetween('created_at', $search['dates']);
        });

        $query->when(isset($search['estatus']), function ($query) use ($search) {
            $query->where('estatus', $search['estatus']);
        });

        $query->when(isset($search['sucursal']), function ($query) use ($search) {
            $query->where('sucursal_id', $search['sucursal']);
        });
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function setCreatedAtAttribute($value)
    {
        if (!$value) { $this->attributes['created_at'] = null; return; }

        // 1) Si viene con offset ISO8601 (ej: "2025-09-10T13:45:00-06:00"), Carbon lo respeta.
        // 2) Si viene "naive" (sin TZ), asumimos que es hora local de Chihuahua.
        $dt = $value instanceof Carbon
            ? $value
            : Carbon::parse($value, config('app.display_timezone', 'America/Chihuahua'));

        $this->attributes['created_at'] = $dt->clone()->utc(); // guardamos en UTC
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function getCreatedAtAttribute($value)
    {
        if (!$value) return null;
        $c = $this->asDateTime($value);           // -> instancia UTC
        return $c->clone()
            ->setTimezone(config('app.display_timezone', 'America/Chihuahua'));
    }
}
