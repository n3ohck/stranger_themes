<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CorteCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cortes',function (Blueprint $table){
            $table->id();
            $table->double('total')->default(0);
            $table->double('efectivo')->default(0);
            $table->double('tarjeta')->default(0);
            $table->double('transferencia')->default(0);
            $table->double('total_caja')->default(0);
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_final');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('apertura_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('sucursal_id')->references('id')->on('sucursales');
            $table->foreign('apertura_id')->references('id')->on('aperturas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cortes');
    }
}
