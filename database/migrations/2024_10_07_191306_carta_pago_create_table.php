<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CartaPagoCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cartas_pagos',function(Blueprint $table){
            $table->id();
            $table->string('nombres');
            $table->string('apellidos');
            $table->double('importe')->default(0);
            $table->text('contenido_carta')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->text('archivo')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->string('hash');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cartas_pagos');
    }
}
