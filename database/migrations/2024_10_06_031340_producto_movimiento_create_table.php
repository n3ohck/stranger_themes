<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProductoMovimientoCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('producto_movimientos', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('producto_id')
                ->constrained('productos')
                ->onDelete('cascade');
            $table
                ->foreignId('user_id')
                ->constrained('users');
            $table->enum('tipo',[
                'entrada',
                'salida'
            ])->index();
            $table->enum('origen',[
                'venta',
                'devolucion',
                'ajuste'
            ])->index();
            $table->text('comentario')->nullable();
            $table->double('cantidad');
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
        Schema::dropIfExists('producto_movimientos');
    }
}
