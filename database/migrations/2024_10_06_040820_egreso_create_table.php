<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EgresoCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->double('monto');
            $table->string('descripcion');
            $table->enum('tipo_pago',[
                'efectivo',
                'tarjeta',
                'transferencia'
            ])->index();
            $table->enum('estatus',[
                'activo',
                'inactivo'
            ])->index();
            $table->string('referencia')->nullable();
            $table->text('imagen')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->foreignId('sucursal_id')
                ->constrained('sucursales');
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
        Schema::dropIfExists('egresos');
    }
}
