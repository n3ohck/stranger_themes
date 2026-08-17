<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAperturaToMovimientosTables extends Migration
{
    /**
     * Amarra al turno de caja las salidas de dinero registradas desde el POS.
     *
     * El corte las contaba por rango de fechas sobre fecha_pago, que es la fecha
     * que declara quien captura y no necesariamente el momento en que el dinero
     * salió del cajón. Con apertura_id, un egreso capturado en el turno cuenta en
     * ese turno sin depender de la fecha que se haya escrito.
     *
     * Queda nullable porque los egresos y pagos capturados desde el panel de
     * administración no pertenecen a ninguna caja; esos se siguen contando por
     * ventana de fechas.
     */
    public function up()
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->unsignedBigInteger('apertura_id')->nullable()->after('sucursal_id');
            $table->foreign('apertura_id')->references('id')->on('aperturas');
        });

        Schema::table('empleado_pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('apertura_id')->nullable()->after('user_id');
            $table->foreign('apertura_id')->references('id')->on('aperturas');
        });
    }

    public function down()
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropForeign(['apertura_id']);
            $table->dropColumn('apertura_id');
        });

        Schema::table('empleado_pagos', function (Blueprint $table) {
            $table->dropForeign(['apertura_id']);
            $table->dropColumn('apertura_id');
        });
    }
}
