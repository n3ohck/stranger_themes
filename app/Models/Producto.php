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
        'precios_por_personas',
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
        'precios_por_personas' => 'array',
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

    /**
     * Rangos de precio por número de personas, limpios y ordenados por `desde`.
     *
     * Cada rango es ['desde' => int, 'hasta' => ?int, 'precio' => float];
     * `hasta` null significa "en adelante". Vacío: precio único.
     */
    public function rangosDePrecio(): array
    {
        $rangos = $this->precios_por_personas;

        // Backpack entrega el repeatable como texto JSON; un registro guardado
        // antes del cast podría haber quedado así.
        if (is_string($rangos)) {
            $rangos = json_decode($rangos, true);
        }

        return collect(is_array($rangos) ? $rangos : [])
            ->filter(fn ($r) => is_array($r) && is_numeric($r['desde'] ?? null) && is_numeric($r['precio'] ?? null))
            ->map(fn ($r) => [
                'desde' => (int) $r['desde'],
                'hasta' => isset($r['hasta']) && $r['hasta'] !== '' ? (int) $r['hasta'] : null,
                'precio' => round((float) $r['precio'], 2),
            ])
            ->sortBy('desde')
            ->values()
            ->all();
    }

    /**
     * Precio por persona para un grupo de $personas.
     *
     * Es la única fuente de verdad del precio de lista: la usan el POS al cobrar
     * y la tienda al apartar. El rango del grupo fija el precio de todas sus
     * personas; si ningún rango aplica, se cobra el precio base. El espejo en
     * JavaScript está en resources/js/shared/precio.js.
     */
    public function precioPara(int $personas): float
    {
        foreach ($this->rangosDePrecio() as $rango) {
            if ($personas >= $rango['desde'] && ($rango['hasta'] === null || $personas <= $rango['hasta'])) {
                return $rango['precio'];
            }
        }

        return (float) $this->precio;
    }

    /** El precio más bajo que puede pagar una persona, para mostrar "desde". */
    public function precioMinimo(): float
    {
        $rangos = $this->rangosDePrecio();

        if ($rangos === []) {
            return (float) $this->precio;
        }

        // Se recorre cada tamaño de grupo posible en vez de tomar el mínimo de los
        // rangos: el precio base solo cuenta si algún tamaño cae fuera de ellos.
        $tope = collect($rangos)->map(fn ($r) => max($r['desde'], $r['hasta'] ?? 0))->max() + 1;

        return (float) collect(range(1, $tope))->map(fn ($n) => $this->precioPara($n))->min();
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    /**
     * Guarda los rangos limpios: Backpack entrega el repeatable como texto JSON,
     * con números como texto y con filas vacías si el usuario agregó una y no la
     * llenó. Sin rangos se guarda NULL (precio único).
     */
    public function setPreciosPorPersonasAttribute($valor)
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }

        $rangos = collect(is_array($valor) ? $valor : [])
            ->filter(fn ($r) => is_array($r) && collect($r)->contains(fn ($v) => $v !== null && $v !== ''))
            ->map(fn ($r) => [
                'desde' => (int) ($r['desde'] ?? 0),
                'hasta' => isset($r['hasta']) && $r['hasta'] !== '' ? (int) $r['hasta'] : null,
                'precio' => round((float) ($r['precio'] ?? 0), 2),
            ])
            ->sortBy('desde')
            ->values();

        $this->attributes['precios_por_personas'] = $rangos->isEmpty() ? null : $rangos->toJson();
    }

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
