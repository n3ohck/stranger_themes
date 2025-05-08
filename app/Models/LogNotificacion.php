<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class LogNotificacion extends Model
{
    use CrudTrait, RevisionableTrait, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'log_notificaciones';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'email',
        'venta_id',
        'producto_id',
        'sucursal_id',
        'motivo'
    ];
    protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function botonReenvio()
    {
        return '<a href="notificacion/'.$this->attributes['id'].'/reenviar" class="btn btn-sm btn-link" data-toggle="tooltip" title="Reenviar"><i class="la la-paper-plane"></i> Reenviar</a>';
    }
    public function venta():BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto():BelongsTo{
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function sucursal():BelongsTo{
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

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
