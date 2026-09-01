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
        static::deleted(function ($obj) {
            Storage::disk('public')->delete($obj->logotipo);
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
    public function setLogotipoAttribute($value)
    {
        $filename = str_replace(' ', '_', strip_tags($this->attributes['razon_social'])) . Carbon::now()->format('Y_m_d_h_i_s');
        $attribute_name = "logotipo";
        $disk = 'public';
        $destination_path = "uploads/logotipos";

        // if the image was erased
        if ($value == null) {
            Storage::disk($disk)->delete($this->{$attribute_name});
            $this->attributes[$attribute_name] = null;
        }

        // if a base64 was sent, store it in the db
        if (Str::startsWith($value, 'data:image')) {
            $image = Image::make($value)->encode('jpg', 90);
            $filename = $filename . '.jpg';
            Storage::disk($disk)->put($destination_path . '/' . $filename, $image->stream());
            Storage::disk($disk)->delete($this->{$attribute_name});

            $public_destination_path = Str::replaceFirst('public/', '', $destination_path);
            $this->attributes[$attribute_name] = $public_destination_path . '/' . $filename;
        }
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function getLogotipoAttribute()
    {
        return ( !$this->attributes['logotipo'] ) ? null : 'storage/'.$this->attributes['logotipo'];
    }
}
