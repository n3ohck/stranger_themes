<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SucursalFilterScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if(!backpack_user()->hasRole('administrador')){
            $builder->where('sucursal_id', backpack_user()->sucursal_id);
        }
    }
}
