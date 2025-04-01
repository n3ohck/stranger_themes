<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Reserva extends Model
{
    use CrudTrait, SoftDeletes, RevisionableTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */
    public $search;
    protected $table = 'reservas';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'producto_id',
        'nombre_cliente',
        'cantidad_personas',
        'fecha',
        'estado',
        'sucursal_id',
        'venta_id'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'fecha'
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
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeSearch($query, $search)
    {
        $this->search = $search;
        $texto = $this->search->query;
        return $query
            ->when($texto, function ($query, $texto) {
                return $query->where('nombre_cliente', 'like', "%$texto%");
            })
            ->when($this->search->start_date, function ($query) {
                return $query->where('fecha', '>=', $this->search->start_date);
            })
            ->when($this->search->end_date, function ($query) {
                return $query->where('fecha', '<=', $this->search->end_date);
            })
            ->when($this->search->status, function ($query) {
                return $query->where('estado', $this->search->status);
            })
            ->when($this->search->venta_id, function ($query) {
                return $query->where('venta_id', $this->search->venta_id);
            })
            ->when($this->search->sucursal_id, function ($query) {
                return $query->where('sucursal_id', $this->search->sucursal_id);
            });
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getFechaAttribute()
    {
        return Carbon::parse($this->attributes['fecha'])->format('Y-m-d H:i:s');
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
