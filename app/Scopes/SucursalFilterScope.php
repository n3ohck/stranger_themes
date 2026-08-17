<?php

namespace App\Scopes;

use App\Support\SucursalActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Aísla los datos por sucursal.
 *
 * Antes preguntaba por hasRole('administrador') en minúscula, pero el rol se
 * llama 'Administrador' y Spatie distingue mayúsculas: la condición era siempre
 * falsa y la exención nunca se aplicaba. Con una sola sucursal daba igual; con
 * dos habría dejado a los administradores convencidos de que la otra sucursal
 * no tiene datos.
 *
 * Ahora la sucursal la resuelve SucursalActiva, que además contempla la que
 * eligió el administrador en el panel.
 */
class SucursalFilterScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $sucursalId = SucursalActiva::id();

        if ($sucursalId === null) {
            return;
        }

        // Se califica con el nombre de la tabla porque varias consultas del panel
        // hacen join contra otras que también tienen sucursal_id.
        $builder->where($model->getTable() . '.sucursal_id', $sucursalId);
    }
}
