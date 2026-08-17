<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Apertura extends Model
{
    use CrudTrait, RevisionableTrait, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */
    public $search;
    protected $table = 'aperturas';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'id',
        'user_id',
        'user_id_cerro',
        'monto_apertura',
        'monto_cierre',
        'estado',
        'billetes',
        'sucursal_id',
        'created_at'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'billetes' => 'array',
        'monto_apertura' => 'double',
        'monto_cierre' => 'double',
        'created_at' => 'datetime',
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
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCierre():BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_cerro');
    }

    public function sucursal():BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    /**
     * Turno de caja abierto de un usuario en su sucursal.
     *
     * El POS admite una sola caja abierta por usuario a la vez; si quedaran
     * varias por un cierre fallido, se toma la más reciente.
     */
    public static function aperturaActiva($user): ?self
    {
        return self::query()
            ->where('user_id', $user->id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('estado', 'abierto')
            ->orderByDesc('id')
            ->first();
    }

    public function scopeSearch($query,$search)
    {
        $this->search = $search;
        return $query
            ->when($search->user_id, function ($query) {
                $query->where('user_id', $this->search->user_id);
            })
            ->when($search->user_id_cerro, function ($query) {
                $query->where('user_id_cerro', $this->search->user_id_cerro);
            })
            ->when($search->estado, function ($query) {
                $query->where('estado', $this->search->estado);
            })
            ->when($search->fecha_apertura, function ($query) {
                $query->whereBetween('created_at', [$this->search->fecha_apertura->startOfDay(), $this->search->fecha_apertura->endOfDay()]);
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
    public function getCreatedAtAttribute()
    {
        return Carbon::parse($this->attributes['created_at'])->format('Y-m-d H:i:s');
    }

}
