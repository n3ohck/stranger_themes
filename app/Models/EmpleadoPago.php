<?php

namespace App\Models;

use App\Scopes\SucursalFilterScope;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'estatus'
    ];
    // protected $hidden = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'fecha_pago'
    ];

    protected $casts = [
        'monto' => 'double'
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        parent::boot();
        static::addGlobalScope(new SucursalFilterScope);
        static::deleting(function ($obj) {
            Storage::disk('pagos')->delete($obj->imagen);
        });
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
        $this->search = $search;
        return  $query
            ->when($this->search->empleado_id, function ($query) {
                $query->where('empleado_id', $this->search->empleado_id);
            })
            ->when($this->search->fecha_pago, function ($query) {
                $query->where('fecha_pago', $this->search->fecha_pago);
            })
            ->when($this->search->estatus, function ($query) {
                $query->where('estatus', $this->search->estatus);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function setArchivoAttribute($value)
    {
        $attribute_name = "imagen";
        $disk = "pagos";
        $destination_path = "pago_cartas";

        $this->uploadFileToDisk($value, $attribute_name, $disk, $destination_path);

        // return $this->attributes[{$attribute_name}]; // uncomment if this is a translatable field
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
