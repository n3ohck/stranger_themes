<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFolioPorSucursal extends Migration
{
    /**
     * Folio con prefijo y consecutivo propio de cada sucursal.
     *
     * El consecutivo vive en la sucursal (folio_consecutivo) y se reparte con un
     * bloqueo de fila, no con MAX()+1. El esquema anterior calculaba el folio a
     * partir de MAX(id) del modelo Venta, que excluye los registros borrados por
     * SoftDeletes: al borrar un bloque de ventas recientes el contador retrocedía
     * y los folios nuevos chocaban con los viejos. Así se generaron 93 folios
     * repetidos entre 741 ventas.
     *
     * ventas.folio_consecutivo queda nullable a propósito: las ventas históricas
     * se quedan en NULL y MySQL no las considera en el índice único, así que la
     * garantía aplica desde la primera venta nueva sin reescribir el pasado.
     */
    public function up()
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('prefijo_folio', 8)->nullable()->after('razon_social');
            $table->unsignedBigInteger('folio_consecutivo')->default(0)->after('prefijo_folio');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('folio_consecutivo')->nullable()->after('folio');

            $table->unique(['sucursal_id', 'folio_consecutivo'], 'ventas_sucursal_consecutivo_unique');
        });

        // Prefijo inicial derivado del nombre, para que ninguna sucursal quede sin
        // uno. Se puede ajustar después desde el panel.
        foreach (DB::table('sucursales')->get(['id', 'razon_social']) as $sucursal) {
            DB::table('sucursales')
                ->where('id', $sucursal->id)
                ->update(['prefijo_folio' => $this->prefijoSugerido($sucursal->razon_social, $sucursal->id)]);
        }
    }

    /**
     * Iniciales de las palabras significativas del nombre; si no alcanza, se
     * completa con el id para que nunca haya dos prefijos iguales.
     */
    private function prefijoSugerido(?string $nombre, int $id): string
    {
        $ignorar = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'stranger', 'themes'];

        $iniciales = collect(preg_split('/\s+/', mb_strtolower($nombre ?? '')))
            ->filter(fn ($palabra) => $palabra !== '' && ! in_array($palabra, $ignorar, true))
            ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->take(3)
            ->implode('');

        return $iniciales !== '' ? $iniciales : 'S' . $id;
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_sucursal_consecutivo_unique');
            $table->dropColumn('folio_consecutivo');
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['prefijo_folio', 'folio_consecutivo']);
        });
    }
}
