<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Venturecraft\Revisionable\RevisionableTrait;

class EmpleadoPago extends Model
{
    use CrudTrait, RevisionableTrait, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    public $search;
    protected $table = 'empleado_pagos';
    protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'empleado_id',
        'fecha_pago',
        'imagen',
        'monto',
        'estatus',
        'created_at'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'fecha_pago'
    ];

    protected $casts = [
        'monto' => 'double',
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        parent::boot();
        static::addGlobalScope(function ($query) {
            $query->whereHas('empleado', function ($query) {
                $query->where('sucursal_id', backpack_user()->sucursal_id);
            });
        });
        static::deleting(function ($obj) {
            Storage::disk('pagos')->delete($obj->imagen);
        });
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
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeBySucursal($query, $sucursalId)
    {
        $query
            ->whereHas('empleado', function ($query) use ($sucursalId) {
                $query
                    ->where('sucursal_id', $sucursalId);
            });
    }

    public function scopeSearch($query, $search)
    {
        return $query
            ->when(data_get($search, 'empleado_id'), function ($q, $empleadoId) {
                $q->where('empleado_id', $empleadoId);
            })
            ->when(data_get($search, 'fecha_pago'), function ($q, $fecha) {
                $tz = config('app.user_timezone', 'America/Chihuahua');

                $start = Carbon::parse($fecha, $tz)->startOfDay()->utc();
                $end   = Carbon::parse($fecha, $tz)->endOfDay()->utc();

                $q->whereBetween('fecha_pago', [$start, $end]);
            })
            ->when(data_get($search, 'estatus'), function ($q, $estatus) {
                $q->where('estatus', $estatus);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function setImagenAttribute($value)
    {
        $attribute_name = "imagen";
        $disk = "pagos";
        $destination_path = "egresos";

        $this->uploadFileToDisk($value, $attribute_name, $disk, $destination_path);
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
