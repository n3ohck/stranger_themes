<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Venturecraft\Revisionable\RevisionableTrait;

class PagoCarta extends Model
{
    use CrudTrait, SoftDeletes, RevisionableTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'pago_cartas';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'importe',
        'contenido_adicional',
        'fecha_documento',
        'fecha_pago',
        'archivo',
        'hash',
        'user_id',
        'pago_concepto_id',
        'sucursal_id',
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'fecha_documento',
        'fecha_pago'
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function pdfButton()
    {
        return '<a href="/admin/pago-carta/'.$this->attributes['id'].'/pdf" target="_blank" class="btn btn-sm btn-link"><i class="la la-file-pdf"></i> PDF</a>';
    }
    protected static function booted()
    {
        static::addGlobalScope(new SucursalFilterScope);
        parent::boot();
        static::creating(function($obj) {
            $obj->sucursal_id = backpack_user()->sucursal_id;
            $obj->hash = md5($obj->importe.$obj->fecha_documento.$obj->fecha_pago.$obj->pago_concepto_id.$obj->sucursal_id.$obj->user_id);
        });
        static::deleting(function($obj) {
            Storage::disk('pagos')->delete($obj->archivo);
        });
    }

    public function setArchivoAttribute($value)
    {
        $attribute_name = "archivo";
        $disk = "pagos";
        $destination_path = "pago_cartas";

        $this->uploadFileToDisk($value, $attribute_name, $disk, $destination_path);

        // return $this->attributes[{$attribute_name}]; // uncomment if this is a translatable field
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function sucursal():BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function pagoConcepto():BelongsTo
    {
        return $this->belongsTo(PagoConcepto::class, 'pago_concepto_id');
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

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
