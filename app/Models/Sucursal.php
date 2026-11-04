<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use Venturecraft\Revisionable\RevisionableTrait;

class Sucursal extends Model
{
    use CrudTrait, SoftDeletes, RevisionableTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'sucursales';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'razon_social',
        'prefijo_folio',
        'rfc',
        'email',
        'telefono',
        'direccion',
        'logotipo',
        'horarios',
        'ubicacion'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'horarios' => 'array'
    ];


    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public static function boot()
    {
        parent::boot();
        // Solo al borrado definitivo: con SoftDeletes, `deleted` también corre al
        // borrado lógico y la sucursal restaurada se quedaría sin logo.
        static::forceDeleted(function ($obj) {
            // Ruta cruda: el accessor le antepone `storage/` y así no existe en el disco.
            if ($ruta = $obj->getRawOriginal('logotipo')) {
                Storage::disk('public')->delete($ruta);
            }
        });
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    /**
     * El prefijo se guarda siempre en mayúsculas para que los folios se vean
     * iguales sin importar cómo lo haya escrito quien dio de alta la sucursal.
     */
    public function setPrefijoFolioAttribute($value)
    {
        $this->attributes['prefijo_folio'] = $value ? mb_strtoupper(trim($value)) : null;
    }

    /**
     * Entrega el siguiente folio de la sucursal y avanza su consecutivo.
     *
     * El bloqueo de fila serializa a dos cajas de la misma sucursal vendiendo al
     * mismo tiempo: la segunda espera a que la primera confirme. No se usa
     * MAX()+1 sobre ventas porque el modelo excluye los registros con SoftDeletes
     * y el contador retrocedía al borrar ventas, generando folios repetidos.
     *
     * El índice único ventas(sucursal_id, folio_consecutivo) es la red de
     * seguridad: si algún día se rompiera la serialización, la inserción falla en
     * vez de duplicar en silencio.
     *
     * @return array{folio: string, consecutivo: int}
     */
    public static function tomarFolio(int $sucursalId): array
    {
        return DB::transaction(function () use ($sucursalId) {
            $sucursal = DB::table('sucursales')
                ->where('id', $sucursalId)
                ->lockForUpdate()
                ->first(['id', 'prefijo_folio', 'folio_consecutivo']);

            if (! $sucursal) {
                throw new \RuntimeException("La sucursal {$sucursalId} no existe.");
            }

            $consecutivo = (int) $sucursal->folio_consecutivo + 1;

            DB::table('sucursales')
                ->where('id', $sucursalId)
                ->update(['folio_consecutivo' => $consecutivo]);

            $prefijo = $sucursal->prefijo_folio ?: 'S' . $sucursal->id;

            return [
                'folio' => $prefijo . '-' . $consecutivo,
                'consecutivo' => $consecutivo,
            ];
        });
    }

    public function users():HasMany
    {
        return $this->hasMany(User::class,'sucursal_id','id');
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    /**
     * Guarda el logotipo que manda el campo `image` de Backpack (base64).
     *
     * Se lee la ruta cruda de `$this->attributes`, no `$this->logotipo`: el
     * accessor le antepone `storage/` y esa ruta no existe en el disco, y en una
     * sucursal nueva la llave ni siquiera está (por eso fallaba el alta).
     */
    public function setLogotipoAttribute($value)
    {
        $disk = 'public';
        $destination_path = "uploads/logotipos";
        $anterior = $this->attributes['logotipo'] ?? null;

        // Se quitó la imagen
        if (empty($value)) {
            if ($anterior) {
                Storage::disk($disk)->delete($anterior);
            }
            $this->attributes['logotipo'] = null;
            return;
        }

        // Llegó una imagen nueva en base64. Cualquier otro valor es la ruta que
        // ya estaba guardada y se deja como está.
        if (Str::startsWith($value, 'data:image')) {
            $nombre = Str::slug(strip_tags($this->attributes['razon_social'] ?? 'sucursal'), '_')
                . Carbon::now()->format('Y_m_d_h_i_s') . '.jpg';

            $image = Image::make($value)->encode('jpg', 90);
            Storage::disk($disk)->put($destination_path . '/' . $nombre, $image->stream());

            if ($anterior) {
                Storage::disk($disk)->delete($anterior);
            }

            $this->attributes['logotipo'] = $destination_path . '/' . $nombre;
        }
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function getLogotipoAttribute()
    {
        $ruta = $this->attributes['logotipo'] ?? null;

        return $ruta ? 'storage/' . $ruta : null;
    }
}
