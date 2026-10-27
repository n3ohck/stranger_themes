<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, conParametros } from '../api/cliente';
import { useCatalogo } from '../stores/catalogo';
import { aIsoFecha, dinero, fechaLarga, sumarDias } from '../utils/formato';
import Icono from '../components/Icono.vue';
import ModalCobro from '../components/ModalCobro.vue';
import TicketVenta from '../components/TicketVenta.vue';

const catalogo = useCatalogo();

const hoy = new Date();
hoy.setHours(0, 0, 0, 0);

// Arranca en el día de hoy: es lo que se necesita ver al llegar al mostrador.
const dia = ref(new Date(hoy));
const agenda = ref(null);
const cargando = ref(true);
const error = ref('');
const aviso = ref('');

const arrastrando = ref(null);
const horaEncima = ref('');

const editando = ref(null);
const guardando = ref(false);
const errorEdicion = ref('');

// Subir participantes se cobra como una venta de ampliación del turno; bajarlos
// solo ajusta la reservación, sin reembolso.
const cotizacion = ref(null);
const cotizando = ref(false);
const cobrando = ref(false);
const errorCobro = ref('');
const ticketAmpliacion = ref(null);

const sube = computed(() =>
    !!editando.value && Number(editando.value.personas) > editando.value.original.personas
);

let consulta = 0;

watch(() => editando.value && sube.value && Number(editando.value.personas), async (personas) => {
    cotizacion.value = null;
    if (!personas) return;

    const esta = ++consulta;
    cotizando.value = true;

    try {
        const datos = await api.get(conParametros(`reservas/${editando.value.id}/ampliacion`, { personas }));
        if (esta === consulta) cotizacion.value = datos;
    } catch (e) {
        if (esta === consulta) errorEdicion.value = e.listaErrores?.[0] || e.message;
    } finally {
        if (esta === consulta) cotizando.value = false;
    }
});

const fechaIso = computed(() => aIsoFecha(dia.value));
const esHoy = computed(() => fechaIso.value === aIsoFecha(hoy));

const recorridos = computed(() =>
    catalogo.productos.filter((p) => p.requiere_reservacion)
);

async function cargar() {
    cargando.value = true;
    error.value = '';

    try {
        agenda.value = await api.get(conParametros('reservas/dia', { fecha: fechaIso.value }));
    } catch (e) {
        error.value = e.listaErrores?.[0] || e.message || 'No se pudo cargar la agenda.';
    } finally {
        cargando.value = false;
    }
}

onMounted(async () => {
    await catalogo.cargar();
    cargar();
});

function mover(dias) {
    dia.value = sumarDias(dia.value, dias);
    cargar();
}

function irAHoy() {
    dia.value = new Date(hoy);
    cargar();
}

function cambiarFecha(valor) {
    if (!valor) return;
    const [a, m, d] = valor.split('-').map(Number);
    dia.value = new Date(a, m - 1, d);
    cargar();
}

/* ---------- Arrastrar para cambiar de hora ---------- */

function iniciarArrastre(reservacion, evento) {
    arrastrando.value = reservacion;
    evento.dataTransfer.effectAllowed = 'move';
    // Firefox no inicia el arrastre si no se escribe algo en dataTransfer.
    evento.dataTransfer.setData('text/plain', String(reservacion.id));
}

function terminarArrastre() {
    arrastrando.value = null;
    horaEncima.value = '';
}

async function soltarEn(hora) {
    const reservacion = arrastrando.value;
    terminarArrastre();

    if (!reservacion || reservacion.hora === hora) return;

    await aplicar(reservacion.id, { hora }, `${reservacion.producto} movido a las ${hora}.`);
}

/* ---------- Edición ---------- */

function editar(reservacion) {
    editando.value = {
        id: reservacion.id,
        producto_id: reservacion.producto_id,
        personas: reservacion.personas,
        hora: reservacion.hora,
        nombre_cliente: reservacion.cliente || '',
        original: reservacion,
    };
    errorEdicion.value = '';
}

function confirmarEdicion() {
    if (sube.value) {
        errorCobro.value = '';
        cobrando.value = true;
        return;
    }

    guardarEdicion();
}

/** Cobra las personas extra y luego aplica el resto de los cambios, si hubo. */
async function cobrarAmpliacion({ pagos }) {
    const e = editando.value;
    errorCobro.value = '';
    guardando.value = true;

    try {
        const datos = await api.post(`reservas/${e.id}/ampliacion`, {
            personas: Number(e.personas),
            pagos,
        });

        const otrosCambios = e.producto_id !== e.original.producto_id
            || e.hora !== e.original.hora
            || (e.nombre_cliente || '') !== (e.original.cliente || '');

        if (otrosCambios) {
            await api.put(`reservas/${e.id}`, {
                producto_id: e.producto_id,
                hora: e.hora,
                nombre_cliente: e.nombre_cliente || null,
            });
        }

        cobrando.value = false;
        editando.value = null;
        ticketAmpliacion.value = datos.venta;
        cargar();
    } catch (err) {
        errorCobro.value = err.listaErrores?.[0] || err.message;
    } finally {
        guardando.value = false;
    }
}

async function guardarEdicion() {
    const e = editando.value;
    errorEdicion.value = '';
    guardando.value = true;

    try {
        await api.put(`reservas/${e.id}`, {
            producto_id: e.producto_id,
            personas: Number(e.personas),
            hora: e.hora,
            nombre_cliente: e.nombre_cliente || null,
        });

        editando.value = null;
        aviso.value = 'Reservación actualizada.';
        setTimeout(() => (aviso.value = ''), 4000);
        cargar();
    } catch (err) {
        errorEdicion.value = err.listaErrores?.[0] || err.message;
    } finally {
        guardando.value = false;
    }
}

async function aplicar(id, cambios, mensaje) {
    try {
        await api.put(`reservas/${id}`, cambios);
        aviso.value = mensaje;
        setTimeout(() => (aviso.value = ''), 4000);
        cargar();
    } catch (e) {
        error.value = e.listaErrores?.[0] || e.message;
        setTimeout(() => (error.value = ''), 6000);
        cargar();
    }
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 space-y-3 border-b border-noche-700 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1">
                    <button type="button" class="btn-neutro h-10 min-h-0 w-10 px-0" aria-label="Día anterior" @click="mover(-1)">
                        <Icono nombre="izquierda" clase="h-4 w-4" />
                    </button>
                    <button type="button" class="btn-neutro h-10 min-h-0 w-10 px-0" aria-label="Día siguiente" @click="mover(1)">
                        <Icono nombre="derecha" clase="h-4 w-4" />
                    </button>
                    <button type="button" class="btn-fantasma h-10 min-h-0 px-3 text-sm" :disabled="esHoy" @click="irAHoy">Hoy</button>

                    <h1 class="ml-2 text-base font-bold text-slate-100 first-letter:uppercase">
                        {{ fechaLarga(fechaIso) }}
                        <span v-if="esHoy" class="ml-1 text-xs font-semibold text-acento-400">hoy</span>
                    </h1>
                </div>

                <input :value="fechaIso" type="date" class="campo w-auto py-2 text-sm"
                       @change="cambiarFecha($event.target.value)">
            </div>

            <div v-if="agenda" class="flex flex-wrap gap-2 text-sm">
                <span class="chip bg-noche-700 text-slate-300">{{ agenda.totales.reservaciones }} reservaciones</span>
                <span class="chip bg-acento-600/25 text-acento-400">{{ agenda.totales.personas }} personas</span>
                <span class="chip bg-sky-600/20 text-sky-400">
                    <Icono nombre="globo" clase="h-3.5 w-3.5" />{{ agenda.totales.desde_web }} en línea
                </span>
                <span class="chip bg-emerald-600/20 text-emerald-400">
                    <Icono nombre="tienda" clase="h-3.5 w-3.5" />{{ agenda.totales.desde_pos }} mostrador
                </span>
                <span class="chip bg-noche-700 text-slate-500">salidas cada {{ agenda.intervalo_minutos }} min</span>
            </div>

            <p v-if="aviso" class="rounded-lg bg-emerald-600/15 px-3 py-2 text-sm text-emerald-400">{{ aviso }}</p>
            <p v-if="error" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">{{ error }}</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
            <p v-if="cargando" class="py-12 text-center text-slate-500">Cargando agenda…</p>

            <p v-else-if="agenda && !agenda.abre && !agenda.horarios.length" class="py-12 text-center text-slate-500">
                La sucursal no abre este día.
            </p>

            <div v-else-if="agenda" class="space-y-1.5">
                <!--
                    Se listan todas las horas en que puede salir un recorrido, con o
                    sin reservación: los huecos son la información que se usa para
                    ofrecer lugar por teléfono.
                -->
                <div
                    v-for="h in agenda.horarios" :key="h.inicio"
                    class="flex gap-3 rounded-lg border p-2 transition"
                    :class="horaEncima === h.inicio
                        ? 'border-acento-500 bg-acento-500/10'
                        : 'border-noche-700 bg-noche-800/40'"
                    @dragover.prevent="horaEncima = h.inicio"
                    @dragleave="horaEncima === h.inicio && (horaEncima = '')"
                    @drop.prevent="soltarEn(h.inicio)"
                >
                    <div class="w-20 shrink-0 pt-1 text-right">
                        <p class="tabular text-sm font-bold text-slate-200">{{ h.etiqueta }}</p>
                        <p v-if="!h.en_rejilla" class="text-[10px] text-amber-400">fuera de horario</p>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p v-if="!h.reservaciones.length" class="py-2 text-xs text-slate-600">Libre</p>

                        <div v-if="h.reservaciones.length" class="mb-1.5 flex flex-wrap gap-1.5">
                            <span
                                v-for="o in h.ocupacion" :key="o.producto"
                                class="chip"
                                :class="o.capacidad && o.personas > o.capacidad
                                    ? 'bg-amber-500/15 text-amber-400'
                                    : 'bg-noche-700 text-slate-400'"
                                :title="o.grupos > 1 ? `${o.grupos} grupos juntos` : ''"
                            >
                                {{ o.producto }} · {{ o.personas }}<template v-if="o.capacidad">/{{ o.capacidad }}</template>
                            </span>
                        </div>

                        <div v-if="h.reservaciones.length" class="flex flex-wrap gap-2">
                            <button
                                v-for="r in h.reservaciones" :key="r.id"
                                type="button"
                                draggable="true"
                                class="flex min-w-[13rem] cursor-grab items-center gap-2 rounded border border-noche-600 bg-noche-800 px-3 py-2 text-left transition hover:border-acento-500 active:cursor-grabbing"
                                :class="arrastrando?.id === r.id ? 'opacity-40' : ''"
                                @dragstart="iniciarArrastre(r, $event)"
                                @dragend="terminarArrastre"
                                @click="editar(r)"
                            >
                                <span
                                    class="chip shrink-0"
                                    :class="r.origen === 'web' ? 'bg-sky-600/20 text-sky-400' : 'bg-emerald-600/20 text-emerald-400'"
                                    :title="r.origen === 'web' ? 'Comprada en el sitio web' : 'Vendida en mostrador'"
                                >
                                    <Icono :nombre="r.origen === 'web' ? 'globo' : 'tienda'" clase="h-3.5 w-3.5" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-100">{{ r.producto }}</span>
                                    <span class="block truncate text-xs text-slate-500">
                                        {{ r.cliente || 'Sin nombre' }}
                                        <template v-if="r.venta"> · {{ r.venta.folio }}</template>
                                    </span>
                                </span>

                                <span
                                    class="chip shrink-0 bg-noche-700 text-slate-300"
                                    :title="r.venta?.ampliaciones?.length ? 'Se agregaron personas y se cobraron aparte' : ''"
                                >
                                    {{ r.personas }}<template v-if="r.venta?.ampliaciones?.length">*</template>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <p class="pt-2 text-center text-xs text-slate-600">
                    Arrastra una reservación a otra hora para moverla, o tócala para ajustar
                    participantes, recorrido o nombre.
                </p>
            </div>
        </div>

        <!-- Ajustar reservación -->
        <div v-if="editando" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4"
             @click.self="editando = null">
            <div class="tarjeta w-full max-w-md rounded-b-none p-5 sm:rounded-b-xl">
                <h2 class="text-lg font-bold text-slate-100">Ajustar reservación</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ editando.original.venta?.folio || 'Sin venta asociada' }}
                    <template v-if="editando.original.venta"> · {{ dinero(editando.original.venta.total) }}</template>
                </p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="etiqueta text-xs">Recorrido</label>
                        <select v-model.number="editando.producto_id" class="campo py-2 text-sm">
                            <option v-for="p in recorridos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="etiqueta text-xs">Hora</label>
                            <select v-model="editando.hora" class="campo py-2 text-sm">
                                <option v-for="h in agenda.horarios" :key="h.inicio" :value="h.inicio">
                                    {{ h.etiqueta }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="etiqueta text-xs">Participantes</label>
                            <input v-model.number="editando.personas" type="number" min="1" class="campo py-2 text-sm">
                        </div>
                    </div>

                    <!-- Subir personas se cobra aparte -->
                    <div v-if="sube" class="rounded-lg border border-acento-500/40 bg-acento-500/10 px-3 py-2 text-sm">
                        <p v-if="cotizando" class="text-slate-400">Calculando…</p>
                        <template v-else-if="cotizacion">
                            <p class="font-semibold text-slate-100">
                                +{{ cotizacion.extra }} {{ cotizacion.extra === 1 ? 'persona' : 'personas' }}
                                · {{ dinero(cotizacion.total) }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ dinero(cotizacion.total_unitario) }} c/u de {{ cotizacion.producto }}, lo mismo que en la venta original
                                <template v-if="cotizacion.codigo_descuento"> (con {{ cotizacion.codigo_descuento }})</template>.
                            </p>
                            <p v-if="cotizacion.recorridos.length > 1" class="mt-1 text-xs text-slate-400">
                                Se suman a todos los recorridos del paquete: {{ cotizacion.recorridos.join(', ') }}.
                            </p>
                        </template>
                    </div>

                    <div>
                        <label class="etiqueta text-xs">Nombre del cliente</label>
                        <input v-model="editando.nombre_cliente" type="text" class="campo py-2 text-sm">
                    </div>
                </div>

                <div v-if="editando.original.venta?.ampliaciones?.length" class="mt-4 text-xs text-slate-400">
                    <p class="etiqueta text-xs">Ampliaciones cobradas</p>
                    <p v-for="a in editando.original.venta.ampliaciones" :key="a.folio">
                        {{ a.folio }} · +{{ a.personas }} {{ a.personas === 1 ? 'persona' : 'personas' }} · {{ dinero(a.total) }}
                    </p>
                </div>

                <p class="mt-4 rounded-lg bg-noche-900 px-3 py-2 text-xs text-slate-500">
                    Subir participantes se cobra aparte, como ampliación de la venta. Bajarlos o
                    cambiar el recorrido no modifica la venta ni devuelve dinero.
                </p>

                <p v-if="errorEdicion" class="mt-3 rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">
                    {{ errorEdicion }}
                </p>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="btn-neutro flex-1" @click="editando = null">Cancelar</button>
                    <button type="button" class="btn-primario flex-1"
                            :disabled="guardando || (sube && !cotizacion)" @click="confirmarEdicion">
                        {{ guardando ? 'Guardando…' : sube && cotizacion ? `Cobrar ${dinero(cotizacion.total)} y actualizar` : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>

        <ModalCobro
            v-if="cobrando && cotizacion"
            :total="cotizacion.total"
            :guardando="guardando"
            :error="errorCobro"
            @cerrar="cobrando = false"
            @cobrar="cobrarAmpliacion"
        />

        <TicketVenta v-if="ticketAmpliacion" :venta="ticketAmpliacion" @cerrar="ticketAmpliacion = null" />
    </div>
</template>
