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

        // Una diferencia se cobra en mostrador, nunca en línea, aunque alguien
        // marque la casilla en el panel.
        static::saving(function (Producto $producto) {
            if (in_array($producto->getAttributes()['tipo'] ?? null, self::TIPOS_SOLO_MOSTRADOR, true)) {
                $producto->visible_en_tienda = false;
            }
        });
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
     * Tipos que solo se venden en mostrador. Las diferencias son el ajuste que
     * cobra el cajero en persona, así que no aparecen en ningún canal en línea.
     */
    public const TIPOS_SOLO_MOSTRADOR = ['diferencias'];

    /**
     * Lo que se puede comprar en línea: marcado para la tienda, de un tipo que se
     * vende ahí y sin borrar.
     *
     * El filtro de borrado va explícito porque los llamadores públicos quitan el
     * scope de sucursal, y withoutGlobalScopes() también quita el de SoftDeletes:
     * así se colaban en /comprar los paquetes "DIFERENCIA" borrados en 2024 que
     * habían quedado con la casilla marcada.
     */
    public function scopeVendibleEnLinea($query)
    {
        return $query
            ->whereNull($this->getQualifiedDeletedAtColumn())
            ->where('visible_en_tienda', true)
            ->whereNotIn('tipo', self::TIPOS_SOLO_MOSTRADOR);
    }

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
