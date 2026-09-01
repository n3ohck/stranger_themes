<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPorcentajeAComprasPendientes extends Migration
{
    /**
     * Se guarda el porcentaje con el que se apartó la compra.
     *
     * Podría deducirse del monto, pero el porcentaje del código puede cambiarse en el
     * panel entre que el cliente aparta y termina de pagar; congelarlo aquí evita que
     * la venta quede registrada con un porcentaje que no es el que se le cobró.
     */
    public function up()
    {
        Schema::table('compras_pendientes', function (Blueprint $table) {
            $table->decimal('porcentaje_descuento', 5, 2)->default(0)->after('descuento');
        });
    }

    public function down()
    {
        Schema::table('compras_pendientes', function (Blueprint $table) {
            $table->dropColumn('porcentaje_descuento');
        });
    }
}
