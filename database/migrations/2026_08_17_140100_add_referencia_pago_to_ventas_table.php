<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferenciaPagoToVentasTable extends Migration
{
    /**
     * Cierra el duplicado de venta online a nivel de base de datos.
     *
     * No se puso el índice único sobre venta_pagos(tipo, referencia) porque ahí
     * ya existen 36 referencias repetidas que se decidió conservar. Esta columna
     * arranca en NULL para las 4,579 ventas históricas, y MySQL no considera los
     * NULL en un índice único: el pasado no estorba y toda venta online nueva
     * queda protegida por la base, no solo por un exists() en PHP que dos
     * peticiones simultáneas pueden esquivar.
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('referencia_pago')->nullable()->after('origen');

            $table->unique('referencia_pago', 'ventas_referencia_pago_unique');
        });
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_referencia_pago_unique');
            $table->dropColumn('referencia_pago');
        });
    }
}
