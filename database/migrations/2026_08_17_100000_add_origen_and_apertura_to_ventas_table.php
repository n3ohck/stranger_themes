<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOrigenAndAperturaToVentasTable extends Migration
{
    /**
     * origen: distingue venta de mostrador (pos) de venta del sitio web (web).
     *         Antes se infería con whereHas('pagos', tipo = online), que obliga a
     *         un subquery en cada reporte y no distingue una venta de mostrador
     *         cobrada con referencia de una venta realmente originada en la web.
     *
     * apertura_id: amarra la venta al turno de caja. Sin esto, "ventas del turno"
     *              y el corte se calculan por rango de fechas, que falla cuando un
     *              turno cruza medianoche o cuando hay dos cajas en la sucursal.
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->enum('origen', ['pos', 'web'])->default('pos')->after('sucursal_id')->index();
            $table->unsignedBigInteger('apertura_id')->nullable()->after('origen');

            $table->foreign('apertura_id')->references('id')->on('aperturas');
        });

        // Backfill: toda venta con un pago online proviene del sitio web.
        DB::table('ventas')
            ->whereIn('id', function ($query) {
                $query->select('venta_id')
                    ->from('venta_pagos')
                    ->where('tipo', 'online');
            })
            ->update(['origen' => 'web']);
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['apertura_id']);
            $table->dropColumn(['origen', 'apertura_id']);
        });
    }
}
