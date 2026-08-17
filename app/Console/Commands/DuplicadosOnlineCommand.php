<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico de ventas online duplicadas.
 *
 * Solo lee y reporta. No borra ni modifica nada: cada fila listada es una venta
 * real con dinero asociado y la decisión de cuál conservar es del negocio.
 *
 * Compartir referencia NO implica ser un duplicado. Se distinguen tres casos:
 *
 *   RESUELTO   Solo queda una venta activa en el grupo; las demás ya están
 *              canceladas y no suman a los reportes. No hay nada que hacer.
 *
 *   DUPLICADO  Varias activas con contenido idéntico (mismas líneas y mismas
 *              reservaciones). Es el doble envío: se conserva la de id más bajo.
 *
 *   REVISAR    Varias activas con contenido distinto. Suele ser una
 *              reprogramación en la que se reutilizó el mismo cobro, y ahí la
 *              venta buena puede ser la más reciente. Requiere criterio humano.
 */
class DuplicadosOnlineCommand extends Command
{
    protected $signature = 'pos:duplicados-online {--csv= : Ruta donde exportar el detalle}';

    protected $description = 'Lista las ventas online duplicadas por referencia de pago';

    public function handle()
    {
        $referencias = DB::table('venta_pagos')
            ->select('referencia')
            ->where('tipo', 'online')
            ->whereNotNull('referencia')
            ->where('referencia', '!=', '')
            ->groupBy('referencia')
            ->havingRaw('COUNT(DISTINCT venta_id) > 1')
            ->pluck('referencia');

        if ($referencias->isEmpty()) {
            $this->info('No hay referencias de pago online duplicadas.');

            return 0;
        }

        $filas = [];
        $montoDuplicado = 0.0;
        $montoEnRevision = 0.0;
        $ventasDuplicadas = 0;
        $ventasTotales = 0;
        $grupos = ['RESUELTO' => 0, 'DUPLICADO' => 0, 'REVISAR' => 0];

        foreach ($referencias as $referencia) {
            $ventaIds = DB::table('venta_pagos')
                ->where('tipo', 'online')
                ->where('referencia', $referencia)
                ->pluck('venta_id')
                ->unique()
                ->sort()
                ->values();

            $ventas = DB::table('ventas')
                ->leftJoin('sucursales', 'sucursales.id', '=', 'ventas.sucursal_id')
                ->whereIn('ventas.id', $ventaIds)
                ->orderBy('ventas.id')
                ->select('ventas.id', 'ventas.folio', 'ventas.created_at', 'ventas.total', 'ventas.estatus', 'sucursales.razon_social')
                ->get();

            $activas = $ventas->where('estatus', 'activo')->values();
            $caso = $this->clasificar($activas);
            $grupos[$caso]++;

            // Dentro de un grupo duplicado, la superviviente es la activa más antigua.
            $conservar = $caso === 'DUPLICADO' ? $activas->first()->id : null;

            foreach ($ventas as $venta) {
                $ventasTotales++;
                $sugerencia = $this->sugerencia($caso, $venta, $conservar);

                if ($sugerencia === 'duplicada') {
                    $montoDuplicado += (float) $venta->total;
                    $ventasDuplicadas++;
                } elseif ($caso === 'REVISAR' && $venta->estatus === 'activo') {
                    $montoEnRevision += (float) $venta->total;
                }

                $filas[] = [
                    'caso' => $caso,
                    'referencia' => $referencia,
                    'venta_id' => $venta->id,
                    'folio' => $venta->folio,
                    'fecha' => $venta->created_at,
                    'total' => number_format((float) $venta->total, 2),
                    'estatus' => $venta->estatus,
                    'sucursal' => $venta->razon_social,
                    'sugerencia' => $sugerencia,
                ];
            }
        }

        $this->table(
            ['Caso', 'Referencia', 'Venta', 'Folio', 'Fecha', 'Total', 'Estatus', 'Sucursal', 'Sugerencia'],
            $filas
        );

        $this->newLine();
        $this->line(sprintf(
            '%d referencias compartidas por %d ventas.',
            $referencias->count(),
            $ventasTotales
        ));
        $this->line(sprintf('  RESUELTO  %d grupos: ya solo tienen una venta activa, no hay nada que hacer.', $grupos['RESUELTO']));
        $this->warn(sprintf(
            '  DUPLICADO %d grupos: %d ventas sobrantes por $%s inflando los reportes.',
            $grupos['DUPLICADO'],
            $ventasDuplicadas,
            number_format($montoDuplicado, 2)
        ));
        $this->warn(sprintf(
            '  REVISAR   %d grupos ($%s activos): contenido distinto entre las ventas del grupo.',
            $grupos['REVISAR'],
            number_format($montoEnRevision, 2)
        ));
        $this->newLine();
        $this->line('En los grupos REVISAR la venta buena puede ser la más reciente (por ejemplo una');
        $this->line('reprogramación de fecha), así que no se sugiere ninguna: revísalos uno por uno.');
        $this->line('Este comando no modifica nada.');

        if ($ruta = $this->option('csv')) {
            $handle = fopen($ruta, 'w');
            fputcsv($handle, ['caso', 'referencia', 'venta_id', 'folio', 'fecha', 'total', 'estatus', 'sucursal', 'sugerencia']);

            foreach ($filas as $fila) {
                fputcsv($handle, $fila);
            }

            fclose($handle);
            $this->info("Detalle exportado a {$ruta}");
        }

        return 0;
    }

    /**
     * Dos ventas del mismo cobro son el mismo pedido solo si venden lo mismo y
     * agendan lo mismo. Si difieren, alguien rehizo el pedido reutilizando el
     * cobro y la decisión de cuál conservar no se puede automatizar.
     */
    private function clasificar($activas): string
    {
        if ($activas->count() <= 1) {
            return 'RESUELTO';
        }

        $huellas = $activas->map(fn ($venta) => $this->huella($venta->id))->unique();

        return $huellas->count() === 1 ? 'DUPLICADO' : 'REVISAR';
    }

    private function huella(int $ventaId): string
    {
        $lineas = DB::table('venta_productos')
            ->where('venta_id', $ventaId)
            ->orderBy('producto_id')
            ->get(['producto_id', 'cantidad', 'total'])
            ->map(fn ($linea) => "{$linea->producto_id}:{$linea->cantidad}:{$linea->total}")
            ->implode('|');

        $reservas = DB::table('reservas')
            ->where('venta_id', $ventaId)
            ->orderBy('fecha')
            ->get(['producto_id', 'fecha', 'cantidad_personas'])
            ->map(fn ($reserva) => "{$reserva->producto_id}:{$reserva->fecha}:{$reserva->cantidad_personas}")
            ->implode('|');

        return $lineas . '##' . $reservas;
    }

    private function sugerencia(string $caso, $venta, ?int $conservar): string
    {
        if ($venta->estatus !== 'activo') {
            return 'ya cancelada';
        }

        if ($caso === 'RESUELTO') {
            return 'CONSERVAR';
        }

        if ($caso === 'REVISAR') {
            return 'revisar a mano';
        }

        return $venta->id === $conservar ? 'CONSERVAR' : 'duplicada';
    }
}
