<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
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
        'comentario_cancelacion'
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

    public function userCancelacion():BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_cancelacion');
    }

    public function descuento():BelongsTo
    {
        return $this->belongsTo(Descuento::class);
    }

    public function sucursal():BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function productos():HasMany
    {
        return $this->hasMany(VentaProducto::class);
    }

    public function pagos():HasMany
    {
        return $this->hasMany(VentaPago::class);
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeSearch($query,$search)
    {

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
