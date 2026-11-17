<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLiberadaAComprasPendientes extends Migration
{
    /**
     * 'liberada': el cliente regresó de Stripe sin pagar y soltó el horario antes
     * de que venciera el apartado, para elegir otro. Sin esto, su propio apartado
     * le ocupaba los lugares 30 minutos y no podía volver a comprar ese horario.
     */
    public function up()
    {
        DB::statement("ALTER TABLE compras_pendientes MODIFY estado ENUM('apartada', 'pagada', 'expirada', 'fallida', 'liberada') NOT NULL DEFAULT 'apartada'");
    }

    public function down()
    {
        DB::table('compras_pendientes')->where('estado', 'liberada')->update(['estado' => 'expirada']);
        DB::statement("ALTER TABLE compras_pendientes MODIFY estado ENUM('apartada', 'pagada', 'expirada', 'fallida') NOT NULL DEFAULT 'apartada'");
    }
}
