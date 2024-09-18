<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SucursalCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sucursales',function(Blueprint $table){
            $table->id();
            $table->string('razon_social');
            $table->string('rfc',13)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono',10)->nullable();
            $table->text('direccion')->nullable();
            $table->text('logotipo')->nullable();
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
        Schema::dropIfExists('sucursales');
    }
}
