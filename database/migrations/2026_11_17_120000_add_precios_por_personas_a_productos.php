<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreciosPorPersonasAProductos extends Migration
{
    /**
     * Precio por persona que depende del tamaño del grupo:
     *
     *   [{"desde": 1, "hasta": 3, "precio": 350}, {"desde": 4, "hasta": 6, "precio": 250}]
     *
     * `hasta` vacío significa "en adelante". El rango que corresponde al grupo
     * fija el precio de TODAS sus personas (un grupo de 5 paga 5 × 250). Si
     * ningún rango aplica se cobra `productos.precio`, que sigue siendo el precio
     * base. NULL o lista vacía: el producto tiene precio único, como hasta ahora.
     */
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->json('precios_por_personas')->nullable()->after('precio');
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('precios_por_personas');
        });
    }
}
