<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EmpleadoCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empleados', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->enum('estatus', ['activo', 'inactivo'])->default('activo');
            $table->double('salario')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales');
        });

        Schema::create('empleado_pagos', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->timestamp('fecha_pago');
            $table->double('monto');
            $table->text('imagen')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('empleado_id')
                ->references('id')
                ->on('empleados');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('empleado_pagos');
    }
}
