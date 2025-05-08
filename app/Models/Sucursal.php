<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
