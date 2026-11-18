<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ApartadoAlElegirHorario extends Migration
{
    /**
     * El horario se aparta en cuanto el cliente lo elige, antes de que escriba sus
     * datos: nombre y correo llegan después, al pulsar Pagar, así que pasan a ser
     * opcionales en la fila.
     *
     * 'reembolsada': el cliente pagó después de que venció su apartado y para
     * entonces el horario ya no tenía lugar. Se le devuelve el dinero en Stripe y
     * no se crea venta.
     */
    public function up()
    {
        DB::statement('ALTER TABLE compras_pendientes MODIFY nombre VARCHAR(255) NULL');
        DB::statement('ALTER TABLE compras_pendientes MODIFY email VARCHAR(255) NULL');
        DB::statement("ALTER TABLE compras_pendientes MODIFY estado ENUM('apartada', 'pagada', 'expirada', 'fallida', 'liberada', 'reembolsada') NOT NULL DEFAULT 'apartada'");
    }

    public function down()
    {
        DB::table('compras_pendientes')->where('estado', 'reembolsada')->update(['estado' => 'expirada']);
        DB::statement("ALTER TABLE compras_pendientes MODIFY estado ENUM('apartada', 'pagada', 'expirada', 'fallida', 'liberada') NOT NULL DEFAULT 'apartada'");
        DB::table('compras_pendientes')->whereNull('nombre')->update(['nombre' => '']);
        DB::table('compras_pendientes')->whereNull('email')->update(['email' => '']);
        DB::statement('ALTER TABLE compras_pendientes MODIFY nombre VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE compras_pendientes MODIFY email VARCHAR(255) NOT NULL');
    }
}
