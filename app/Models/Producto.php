<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Producto extends Model
{
    use CrudTrait,SoftDeletes,RevisionableTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'productos';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'id',
        'codigo',
        'descripcion',
        'precio',
        'existencia',
        'tipo',
        'sucursal_id',
        'tours',
        'capacidad',
        'duracion_minutos',
        'visible_en_tienda'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'precio' => 'float',
        'existencia' => 'integer',
        'tours' => 'array',
        'capacidad' => 'integer',
        'duracion_minutos' => 'integer',
        'visible_en_tienda' => 'boolean'
    ];

    protected $appends = ['category','name','price','stock'];

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
    public function sucursal():BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    /** Tipos válidos de la columna enum `tipo`. */
    public const TIPOS = ['articulo', 'tour', 'tour_paquete', 'diferencias'];

    /**
     * Sin tipo devuelve el catálogo completo. Antes hacía `return $type` cuando
     * no venía, es decir devolvía null en vez del query, y el llamador reventaba
     * al encadenar orderBy() sobre null.
     */
    public function scopeFilterByType($query, $type = null, $sucursalId = null)
    {
        if (isset($type) && ! in_array($type, self::TIPOS, true)) {
            throw new \Exception('Tipo de producto no válido');
        }

        return $query
            ->when($sucursalId, fn($query) => $query->where('sucursal_id', $sucursalId))
            ->when(isset($type), fn($query) => $query->where('tipo', $type));
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
    public function getCategoryAttribute()
    {
        return $this->attributes['tipo'];
    }

    public function getNameAttribute()
    {
        return $this->attributes['descripcion'];
    }

    public function getPriceAttribute()
    {
        return $this->attributes['precio'];
    }

    public function getStockAttribute()
    {
        return $this->attributes['existencia'];
    }
}
