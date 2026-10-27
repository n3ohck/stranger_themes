<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClaveIdempotenciaToComprasPendientes extends Migration
{
    /**
     * Clave que manda la pantalla de compra en cada intento de pago. Si el mismo
     * intento llega dos veces (doble clic, reintento tras perder la conexión), el
     * servidor devuelve la sesión de Stripe que ya creó en lugar de apartar y
     * cobrar otra vez. El índice único es la red de seguridad ante dos peticiones
     * simultáneas; nullable porque las compras anteriores no la tienen.
     */
    public function up()
    {
        Schema::table('compras_pendientes', function (Blueprint $table) {
            $table->string('clave_idempotencia', 64)->nullable()->after('referencia');
            $table->unique('clave_idempotencia');
        });
    }

    public function down()
    {
        Schema::table('compras_pendientes', function (Blueprint $table) {
            $table->dropUnique(['clave_idempotencia']);
            $table->dropColumn('clave_idempotencia');
        });
    }
}
