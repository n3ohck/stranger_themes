<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCapacidadYDuracionAProductos extends Migration
{
    /**
     * Datos que la tienda necesita para calcular horarios y que hasta ahora solo
     * vivían en el sitio web como texto.
     *
     * capacidad: máximo de participantes por sesión. Cada horario es exclusivo del
     * grupo que lo reserva, así que la capacidad no se usa para sumar grupos sino
     * para rechazar un grupo más grande de lo que cabe.
     *
     * duracion_minutos: cuánto ocupa la sesión. Sirve para encadenar los recorridos
     * de un paquete uno tras otro y para saber qué horarios bloquea una reserva.
     */
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacidad')->nullable()->after('existencia');
            $table->unsignedSmallInteger('duracion_minutos')->nullable()->after('capacidad');
            $table->boolean('visible_en_tienda')->default(false)->after('duracion_minutos');
        });

        // Valores publicados en strangerthemes.com. El resto queda en NULL y no se
        // ofrece en la tienda hasta que alguien los capture desde el panel.
        $catalogo = [
            'Winchester' => ['capacidad' => 8, 'duracion' => 25],
            'Manicomio' => ['capacidad' => 8, 'duracion' => 25],
            'Escape Room' => ['capacidad' => 6, 'duracion' => 25],
        ];

        foreach ($catalogo as $nombre => $datos) {
            DB::table('productos')
                ->where('tipo', 'tour')
                ->where('descripcion', $nombre)
                ->update([
                    'capacidad' => $datos['capacidad'],
                    'duracion_minutos' => $datos['duracion'],
                    'visible_en_tienda' => true,
                ]);
        }

        // Un paquete hereda la capacidad del recorrido más chico que incluye: si uno
        // de sus tours admite 6, el grupo no puede ser de 8.
        foreach (DB::table('productos')->where('tipo', 'tour_paquete')->get() as $paquete) {
            $ids = collect(json_decode($paquete->tours ?? '[]', true) ?: [])
                ->pluck('producto_id')
                ->map(fn ($id) => (int) $id)
                ->filter();

            if ($ids->isEmpty()) {
                continue;
            }

            $tours = DB::table('productos')->whereIn('id', $ids)->get(['capacidad', 'duracion_minutos']);

            if ($tours->whereNotNull('capacidad')->count() !== $ids->count()) {
                continue; // Algún tour del paquete no tiene datos; se deja fuera de la tienda.
            }

            DB::table('productos')->where('id', $paquete->id)->update([
                'capacidad' => $tours->min('capacidad'),
                'duracion_minutos' => $tours->sum('duracion_minutos'),
                'visible_en_tienda' => true,
            ]);
        }
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['capacidad', 'duracion_minutos', 'visible_en_tienda']);
        });
    }
}
