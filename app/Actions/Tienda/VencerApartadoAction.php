<?php

namespace App\Actions\Tienda;

use App\Models\CompraPendiente;
use App\Support\Tienda\PasarelaStripe;
use Illuminate\Support\Facades\Log;

/**
 * Cierra un apartado que ya venció: primero la sesión de Stripe, después la fila.
 *
 * La sesión de Stripe vive al menos 30 minutos (mínimo de Stripe) y el apartado
 * solo 5, así que hay que cerrarla a mano para que nadie pague un horario que ya
 * se liberó. Lo corre tienda:limpiar-pendientes cada minuto y también la consulta
 * del apartado, para no esperar al siguiente minuto.
 */
class VencerApartadoAction
{
    public function __construct(private PasarelaStripe $pasarela)
    {
    }

    /**
     * @return bool true si quedó vencido; false si no tocaba o hay que reintentar.
     */
    public function __invoke(CompraPendiente $compra): bool
    {
        if ($compra->estado !== 'apartada' || $compra->venta_id || $compra->expira_en->isFuture()) {
            return false;
        }

        if ($compra->stripe_session_id) {
            try {
                // Ya se pagó: no se cierra nada. El webhook o el regreso del
                // cliente lo confirman (o lo reembolsan si ya no hay lugar).
                if (! $this->pasarela->cerrarSesion($compra->stripe_session_id)) {
                    return false;
                }
            } catch (\Throwable $e) {
                // Se queda como apartada y se reintenta en el siguiente minuto. Ya
                // no bloquea el horario: el scope `vigentes` ignora lo caducado.
                Log::warning('No se pudo cerrar la sesión de Stripe de un apartado vencido', [
                    'compra' => $compra->referencia,
                    'mensaje' => $e->getMessage(),
                ]);

                return false;
            }
        }

        return (bool) CompraPendiente::query()
            ->whereKey($compra->id)
            ->where('estado', 'apartada')
            ->whereNull('venta_id')
            ->update(['estado' => 'expirada']);
    }
}
