<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VentaCreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users');

            $table->foreignId('user_id_cancelacion')
                ->constrained('users')
                ->nullable();

            $table->foreignId('descuento_id')
                ->constrained('descuentos')
                ->nullable();
            $table->string('folio')->index();
            $table->double('total');
            $table->string('codigo_descuento')->nullable();
            $table->double('descuento')->nullable();
            $table->double('porcentaje_descuento')->nullable();
            $table->enum('estatus', [
                'activo',
                'cancelado'
            ])
                ->default('activo')
                ->index();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->text('comentario_cancelacion')->nullable();
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
        Schema::dropIfExists('ventas');
    }
}
