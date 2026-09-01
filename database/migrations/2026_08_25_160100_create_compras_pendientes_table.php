<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComprasPendientesTable extends Migration
{
    /**
     * Aparta el horario mientras el cliente está pagando.
     *
     * Como cada horario es exclusivo del grupo que lo reserva, sin este apartado dos
     * personas pueden elegir las 18:00 del mismo recorrido, pagar las dos y dejar al
     * negocio con una sobreventa que solo se descubre en mostrador.
     *
     * La reserva de verdad se crea al confirmarse el pago; esto solo bloquea el
     * horario por unos minutos. Lo caducado no bloquea nada, así que un cliente que
     * abandona el pago libera el lugar solo.
     */
    public function up()
    {
        Schema::create('compras_pendientes', function (Blueprint $table) {
            $table->id();
            $table->uuid('referencia')->unique();

            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedSmallInteger('personas');

            // Horarios concretos que se apartan, uno por recorrido del paquete:
            // [{"producto_id":2,"inicio":"2026-09-12 18:00:00"}, ...]
            $table->json('horarios');

            $table->string('nombre');
            $table->string('email');
            $table->string('telefono')->nullable();

            $table->decimal('total', 10, 2);
            $table->string('codigo_descuento')->nullable();
            $table->unsignedBigInteger('descuento_id')->nullable();
            $table->decimal('descuento', 10, 2)->default(0);

            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent')->nullable()->index();

            $table->enum('estado', ['apartada', 'pagada', 'expirada', 'fallida'])
                ->default('apartada')
                ->index();

            $table->unsignedBigInteger('venta_id')->nullable();
            $table->timestamp('expira_en')->index();
            $table->timestamps();

            $table->foreign('sucursal_id')->references('id')->on('sucursales');
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->foreign('venta_id')->references('id')->on('ventas');
        });
    }

    public function down()
    {
        Schema::dropIfExists('compras_pendientes');
    }
}
