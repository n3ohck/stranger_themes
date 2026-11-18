<?php

namespace App\Console\Commands;

use App\Actions\Tienda\VencerApartadoAction;
use App\Models\CompraPendiente;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Vence los apartados que nadie terminó de pagar. Corre cada minuto.
 *
 * No es lo que libera los horarios (de eso se encarga el scope `vigentes`, que ya
 * ignora lo caducado). Lo que sí hace es cerrar la sesión de Stripe del apartado:
 * el apartado dura 5 minutos y la sesión al menos 30 (mínimo de Stripe), así que
 * sin esto se podría pagar un horario que ya se le ofreció a otro.
 */
class LimpiarComprasPendientesCommand extends Command
{
    protected $signature = 'tienda:limpiar-pendientes';

    protected $description = 'Marca como expiradas las compras en línea que no se completaron';

    public function handle(VencerApartadoAction $vencer)
    {
        $expiradas = CompraPendiente::query()
            ->where('estado', 'apartada')
            ->whereNull('venta_id')
            ->where('expira_en', '<=', Carbon::now())
            ->get()
            ->filter(fn (CompraPendiente $compra) => $vencer($compra))
            ->count();

        if ($expiradas) {
            $this->info("{$expiradas} compras sin completar marcadas como expiradas.");
        } else {
            $this->line('No hay compras pendientes por expirar.');
        }

        return 0;
    }
}
