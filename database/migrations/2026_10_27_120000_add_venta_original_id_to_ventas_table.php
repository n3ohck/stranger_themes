<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVentaOriginalIdToVentasTable extends Migration
{
    /**
     * Liga una venta de ampliación con la venta cuya reservación amplía.
     *
     * Cuando un grupo llega con más gente, las personas extra se cobran como una
     * venta nueva del turno (para que el corte cuadre con el cajón) y esta columna
     * deja el registro de a qué venta y reservación pertenecen. Nullable: ninguna
     * venta existente es una ampliación.
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_original_id')->nullable()->after('apertura_id');
            $table->index('venta_original_id');
        });
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['venta_original_id']);
            $table->dropColumn('venta_original_id');
        });
    }
}
