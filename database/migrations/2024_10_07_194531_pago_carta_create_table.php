<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PagoCartaCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pago_cartas',function(Blueprint $table){
            $table->id();
            $table->double('importe')->default(0);
            $table->text('contenido_adicional')->nullable();
            $table->timestamp('fecha_documento')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->text('archivo')->nullable();
            $table->string('hash');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('pago_concepto_id')->constrained();
            $table->unsignedBigInteger('sucursal_id')->index();
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
        //
    }
}
