<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VentaProductoAlterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('venta_productos',function (Blueprint $table){
            $table->unsignedBigInteger('descuento_id')->nullable();
            $table->string('codigo_descuento')->nullable();
            $table->double('descuento')->default(0);
            $table->double('porcentaje_descuento')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
