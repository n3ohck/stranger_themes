<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
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
        'apertura_id'
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
        'total_caja' => 'float'
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
        $query
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
}
