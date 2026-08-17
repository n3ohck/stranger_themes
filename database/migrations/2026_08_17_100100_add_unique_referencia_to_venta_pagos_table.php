<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueReferenciaToVentaPagosTable extends Migration
{
    /**
     * Cierra a nivel de base de datos el duplicado de venta online.
     *
     * Hasta ahora la protección era solo aplicativa (un exists() en VentaAction),
     * que no resiste dos requests simultáneos: ambos consultan antes de que
     * cualquiera inserte, ambos ven "no existe", ambos insertan. El índice único
     * es la única garantía real.
     *
     * Solo aplica a pagos con referencia; efectivo y tarjeta suelen guardar
     * 'N/A' o NULL, y MySQL no considera los NULL en un índice único.
     */
    public function up()
    {
        $duplicados = DB::table('venta_pagos')
            ->select('tipo', 'referencia', DB::raw('COUNT(*) as total'))
            ->whereNotNull('referencia')
            ->where('referencia', '!=', '')
            ->where('referencia', '!=', 'N/A')
            ->groupBy('tipo', 'referencia')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicados > 0) {
            throw new RuntimeException(
                "No se puede crear el índice único: hay {$duplicados} referencias de pago duplicadas en venta_pagos.\n" .
                "Revísalas primero con:  php artisan pos:duplicados-online\n" .
                "Esa lista corresponde a ventas reales duplicadas que además están inflando los reportes."
            );
        }

        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->unique(['tipo', 'referencia'], 'venta_pagos_tipo_referencia_unique');
        });
    }

    public function down()
    {
        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->dropUnique('venta_pagos_tipo_referencia_unique');
        });
    }
}
