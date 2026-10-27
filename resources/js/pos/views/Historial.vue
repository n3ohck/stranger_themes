<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api/cliente';
import { dinero, horaCorta, fechaHora, fechaCorta, aIsoFecha } from '../utils/formato';
import TicketVenta from '../components/TicketVenta.vue';
import Icono from '../components/Icono.vue';

const ventas = ref([]);
const resumen = ref(null);
const apertura = ref(null);
const cargando = ref(true);
const error = ref('');

const ticket = ref(null);
const cancelando = ref(null);
const motivo = ref('');
const errorCancelacion = ref('');
const enviandoCancelacion = ref(false);

const soloActivas = ref(false);

const etiquetaPago = {
    efectivo: 'Efectivo',
    tarjeta: 'Tarjeta',
    transferencia: 'Transferencia',
    online: 'En línea',
};

const colorPago = {
    efectivo: 'bg-emerald-600/20 text-emerald-400',
    tarjeta: 'bg-sky-600/20 text-sky-400',
    transferencia: 'bg-indigo-600/20 text-indigo-400',
    online: 'bg-amber-600/20 text-amber-400',
};

/** Formas de pago de una venta, con su importe, para verlas de un vistazo. */
function formasDePago(venta) {
    const porTipo = {};

    venta.pagos.forEach((p) => {
        porTipo[p.tipo] = (porTipo[p.tipo] || 0) + p.monto;
    });

    // Una venta de cortesía no tiene pagos y se marca como tal.
    if (!Object.keys(porTipo).length) return [{ tipo: 'cortesia', monto: 0 }];

    return Object.entries(porTipo).map(([tipo, monto]) => ({ tipo, monto }));
}

const hoy = aIsoFecha(new Date());

/** Venta en línea con visita hoy: el cliente va a llegar en este turno. */
function vieneHoy(venta) {
    return venta.origen === 'web'
        && venta.reservaciones.some((r) => r.estado !== 'cancelada' && String(r.fecha).startsWith(hoy));
}

const visibles = computed(() =>
    soloActivas.value ? ventas.value.filter((v) => v.estatus === 'activo') : ventas.value
);

async function cargar() {
    cargando.value = true;
    error.value = '';

    try {
        const datos = await api.get('ventas/turno');
        ventas.value = datos.ventas;
        resumen.value = datos.resumen;
        apertura.value = datos.apertura;
    } catch (e) {
        error.value = e.message || 'No se pudo cargar el historial.';
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);

function pedirCancelacion(venta) {
    cancelando.value = venta;
    motivo.value = '';
    errorCancelacion.value = '';
}

async function confirmarCancelacion() {
    errorCancelacion.value = '';
    enviandoCancelacion.value = true;

    try {
        await api.post(`ventas/${cancelando.value.id}/cancelar`, { motivo: motivo.value.trim() });
        cancelando.value = null;
        await cargar();
    } catch (e) {
        errorCancelacion.value = e.listaErrores?.[0] || e.message || 'No se pudo cancelar la venta.';
    } finally {
        enviandoCancelacion.value = false;
    }
}

async function verTicket(venta) {
    const datos = await api.get(`ventas/${venta.id}/ticket`);
    ticket.value = {
        ...datos.ticket,
        // El ticket del servidor trae la misma forma que espera el componente.
        cliente: datos.ticket.cliente,
    };
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 border-b border-noche-700 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold text-slate-100">Ventas del turno</h1>
                    <p v-if="apertura" class="text-xs text-slate-500">
                        Caja abierta {{ fechaHora(apertura.abierta_en) }} · fondo {{ dinero(apertura.monto_apertura) }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-400">
                        <input v-model="soloActivas" type="checkbox" class="h-4 w-4 rounded border-noche-500 bg-noche-800">
                        Solo activas
                    </label>
                    <button type="button" class="btn-neutro min-h-0 px-3 py-2 text-sm" @click="cargar">
                        Actualizar
                    </button>
                </div>
            </div>

            <div v-if="resumen" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Vendido</p>
                    <p class="tabular text-lg font-bold text-slate-100">{{ dinero(resumen.total) }}</p>
                </div>
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Efectivo</p>
                    <p class="tabular text-lg font-bold text-emerald-400">{{ dinero(resumen.efectivo) }}</p>
                </div>
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Tarjeta</p>
                    <p class="tabular text-lg font-bold text-slate-100">{{ dinero(resumen.tarjeta) }}</p>
                </div>
                <div class="tarjeta p-3" title="Compras del sitio web desde que abriste caja. No entran al cajón.">
                    <p class="text-xs uppercase tracking-wide text-slate-500">En línea</p>
                    <p class="tabular text-lg font-bold text-amber-400">
                        {{ dinero(resumen.en_linea) }}
                        <span v-if="resumen.en_linea_cantidad" class="text-sm font-normal text-slate-500">
                            · {{ resumen.en_linea_cantidad }}
                        </span>
                    </p>
                    <p v-if="resumen.visitan_hoy" class="text-xs text-sky-400">
                        {{ resumen.visitan_hoy }} {{ resumen.visitan_hoy === 1 ? 'viene' : 'vienen' }} hoy
                    </p>
                </div>
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Tickets</p>
                    <p class="tabular text-lg font-bold text-slate-100">
                        {{ resumen.cantidad }}
                        <span v-if="resumen.canceladas" class="text-sm font-normal text-sangre-400">
                            · {{ resumen.canceladas }} cancel.
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
            <p v-if="cargando" class="py-12 text-center text-slate-500">Cargando…</p>
            <p v-else-if="error" class="py-12 text-center text-sangre-400">{{ error }}</p>
            <p v-else-if="!visibles.length" class="py-12 text-center text-slate-500">
                Todavía no hay ventas en este turno.
            </p>

            <div v-else class="space-y-2">
                <article
                    v-for="venta in visibles"
                    :key="venta.id"
                    class="tarjeta p-3"
                    :class="venta.estatus === 'cancelado' ? 'opacity-60' : ''"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-100">{{ venta.folio }}</span>
                                <span
                                    v-if="venta.origen === 'web'"
                                    class="chip bg-amber-600/20 text-amber-400"
                                    title="Comprada en el sitio web"
                                >
                                    <Icono nombre="globo" clase="h-3.5 w-3.5" />
                                    En línea
                                </span>
                                <span v-if="vieneHoy(venta)" class="chip bg-sky-600/20 text-sky-400">Viene hoy</span>
                                <span
                                    v-if="venta.ampliacion_de"
                                    class="chip bg-acento-600/25 text-acento-400"
                                    title="Personas agregadas a una reservación ya vendida"
                                >Ampliación de {{ venta.ampliacion_de }}</span>
                                <span
                                    v-if="venta.estatus === 'cancelado'"
                                    class="chip bg-sangre-600/20 text-sangre-400"
                                >Cancelada</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ horaCorta(venta.fecha) }}
                                <span v-if="venta.cliente"> · {{ venta.cliente }}</span>
                            </p>

                            <!-- Cómo se pagó: era lo primero que se buscaba al revisar el turno. -->
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="p in formasDePago(venta)" :key="p.tipo"
                                    class="chip"
                                    :class="p.tipo === 'cortesia'
                                        ? 'bg-acento-600/25 text-acento-400'
                                        : colorPago[p.tipo] || 'bg-noche-700 text-slate-300'"
                                >
                                    <template v-if="p.tipo === 'cortesia'">Cortesía</template>
                                    <template v-else>{{ etiquetaPago[p.tipo] || p.tipo }} {{ dinero(p.monto) }}</template>
                                </span>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="tabular text-lg font-bold text-slate-100">{{ dinero(venta.total) }}</p>
                            <!-- El monto solo no dice nada: al lado va el código aplicado. -->
                            <p v-if="venta.descuento > 0" class="tabular text-xs text-emerald-400">
                                −{{ dinero(venta.descuento) }}
                            </p>
                            <p v-if="venta.descuento > 0 && venta.codigo_descuento" class="text-xs text-emerald-400/80">
                                {{ venta.codigo_descuento }}
                            </p>
                        </div>
                    </div>

                    <ul class="mt-2 space-y-0.5 text-xs text-slate-400">
                        <li v-for="(linea, i) in venta.lineas" :key="i" class="flex justify-between gap-2">
                            <span class="truncate">{{ linea.cantidad }} × {{ linea.producto }}</span>
                            <span class="tabular shrink-0">{{ dinero(linea.total) }}</span>
                        </li>
                    </ul>

                    <!-- Cuándo vienen: en una venta en línea la compra y la visita no son el mismo día. -->
                    <ul v-if="venta.reservaciones.length" class="mt-2 space-y-0.5 text-xs text-acento-400">
                        <li v-for="r in venta.reservaciones" :key="r.id" :class="r.estado === 'cancelada' ? 'line-through opacity-60' : ''">
                            {{ r.producto }} · {{ fechaCorta(r.fecha) }} {{ horaCorta(r.fecha) }} · {{ r.personas }}
                            {{ r.personas === 1 ? 'persona' : 'personas' }}
                        </li>
                    </ul>

                    <p v-if="venta.motivo_cancelacion" class="mt-2 text-xs italic text-slate-500">
                        Motivo: {{ venta.motivo_cancelacion }}
                    </p>

                    <div class="mt-3 flex gap-2">
                        <button type="button" class="btn-neutro min-h-0 flex-1 px-3 py-2 text-sm" @click="verTicket(venta)">
                            <Icono nombre="imprimir" clase="h-4 w-4" />
                            Ticket
                        </button>
                        <!-- Una venta en línea se cancela con la referencia del cobro, desde el panel. -->
                        <button
                            v-if="venta.estatus === 'activo' && venta.origen !== 'web'"
                            type="button"
                            class="btn-fantasma min-h-0 px-3 py-2 text-sm text-sangre-400 hover:bg-sangre-600/15"
                            @click="pedirCancelacion(venta)"
                        >
                            Cancelar
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <!-- Confirmación de cancelación -->
        <div v-if="cancelando" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="tarjeta w-full max-w-md p-5">
                <h2 class="text-lg font-bold text-slate-100">Cancelar {{ cancelando.folio }}</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Se devolverán las existencias y se liberarán las reservaciones de esta venta.
                    La operación no se puede deshacer desde el punto de venta.
                </p>

                <label class="etiqueta mt-4">Motivo de la cancelación</label>
                <textarea
                    v-model="motivo"
                    rows="3"
                    class="campo"
                    placeholder="Ej. el cliente cambió de opinión"
                ></textarea>

                <p v-if="errorCancelacion" class="mt-2 rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">
                    {{ errorCancelacion }}
                </p>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="btn-neutro flex-1" @click="cancelando = null">Volver</button>
                    <button
                        type="button"
                        class="btn-peligro flex-1"
                        :disabled="motivo.trim().length < 3 || enviandoCancelacion"
                        @click="confirmarCancelacion"
                    >
                        {{ enviandoCancelacion ? 'Cancelando…' : 'Cancelar venta' }}
                    </button>
                </div>
            </div>
        </div>

        <TicketVenta v-if="ticket" :venta="ticket" @cerrar="ticket = null" />
    </div>
</template>
