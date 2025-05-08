<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SucursalUbicationAlterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table
                ->text('ubicacion')
                ->nullable()
                ->after('logotipo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });
    }
}
