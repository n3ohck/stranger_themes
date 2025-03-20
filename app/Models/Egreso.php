<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Egreso extends Model
{
    use CrudTrait, SoftDeletes, RevisionableTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */
    public $search;
    protected $table = 'egresos';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'user_id',
        'monto',
        'descripcion',
        'tipo_pago',
        'estatus',
        'referencia',
        'imagen',
        'fecha_pago',
        'sucursal_id'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'fecha_pago'
    ];

    protected $casts = [
        'monto' => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->sucursal_id = backpack_user()->sucursal_id;
        });
        static::deleting(function ($obj) {
            \Storage::disk('pagos')->delete($obj->imagen);
        });
    }

    public function setImagenAttribute($value)
    {
        $attribute_name = "imagen";
        $disk = "pagos";
        $destination_path = "egresos";

        $this->uploadFileToDisk($value, $attribute_name, $disk, $destination_path);
    }

    public function fileButton()
    {
        return '<a href="' . asset('storage/pagos/' . $this->attributes['imagen']) . '" target="_blank" class="btn btn-sm btn-link"><i class="la la-file"></i> Comprobante</a>';
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeSearch($query, $search)
    {
        $this->search = $search;
        return $query
            ->when($this->search->user_id, function ($query) {
                $query->where('user_id', $this->search->user_id);
            })
            ->when($this->search->sucursal_id, function ($query) {
                $query->where('sucursal_id', $this->search->sucursal_id);
            })
            ->when($this->search->tipo_pago, function ($query) {
                $query->where('tipo_pago', $this->search->tipo_pago);
            })
            ->when($this->search->estatus, function ($query) {
                $query->where('estatus', $this->search->estatus);
            })
            ->when($this->search->fecha_pago, function ($query) {
                $query->whereDate('fecha_pago', '>=', $this->search->fecha_pago);
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
