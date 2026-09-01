<?php

namespace App\Console\Commands;

use App\Models\CompraPendiente;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Marca como expiradas las compras que nadie terminó de pagar.
 *
 * No es lo que libera los horarios (de eso se encarga el scope `vigentes`, que ya
 * ignora lo caducado); esto solo deja el estado explícito para que al revisar la
 * tabla se distinga un abandono de una compra en curso.
 */
class LimpiarComprasPendientesCommand extends Command
{
    protected $signature = 'tienda:limpiar-pendientes';

    protected $description = 'Marca como expiradas las compras en línea que no se completaron';

    public function handle()
    {
        $expiradas = CompraPendiente::query()
            ->where('estado', 'apartada')
            ->where('expira_en', '<=', Carbon::now())
            ->update(['estado' => 'expirada']);

        if ($expiradas) {
            $this->info("{$expiradas} compras sin completar marcadas como expiradas.");
        } else {
            $this->line('No hay compras pendientes por expirar.');
        }

        return 0;
    }
}
