<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AperturaCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aperturas', function (Blueprint $table){
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('user_id_cerro')->constrained();
            $table->double('monto_apertura');
            $table->double('monto_cierre')->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->json('billetes')->nullable();
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
        Schema::dropIfExists('aperturas');
    }
}
