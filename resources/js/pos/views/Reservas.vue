<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, conParametros } from '../api/cliente';
import { aIsoFecha, dinero, fechaLarga, horaCorta, inicioDeSemana, sumarDias } from '../utils/formato';
import Icono from '../components/Icono.vue';

const hoy = new Date();
hoy.setHours(0, 0, 0, 0);

const vista = ref('semana'); // 'dia' | 'semana'
const ancla = ref(new Date(hoy));
const dias = ref([]);
const totales = ref(null);
const cargando = ref(true);
const error = ref('');
const seleccionada = ref(null);

const rango = computed(() => {
    if (vista.value === 'dia') {
        return { desde: new Date(ancla.value), hasta: new Date(ancla.value) };
    }

    const desde = inicioDeSemana(ancla.value);
    return { desde, hasta: sumarDias(desde, 6) };
});

const etiquetaRango = computed(() => {
    const { desde, hasta } = rango.value;

    if (vista.value === 'dia') return fechaLarga(desde);

    return `${fechaLarga(desde)} — ${fechaLarga(hasta)}`;
});

/**
 * Se listan todos los días del rango, incluso los vacíos: un hueco en el
 * calendario es información útil para quien agenda por teléfono.
 */
const diasDelRango = computed(() => {
    const { desde, hasta } = rango.value;
    const salida = [];

    for (let d = new Date(desde); d <= hasta; d = sumarDias(d, 1)) {
        const iso = aIsoFecha(d);
        const encontrado = dias.value.find((x) => x.fecha === iso);

        salida.push(encontrado || {
            fecha: iso,
            reservaciones: [],
            total_reservaciones: 0,
            total_personas: 0,
            desde_web: 0,
            desde_pos: 0,
        });
    }

    return salida;
});

async function cargar() {
    cargando.value = true;
    error.value = '';

    try {
        const { desde, hasta } = rango.value;
        const datos = await api.get(conParametros('reservas', {
            desde: aIsoFecha(desde),
            hasta: aIsoFecha(hasta),
        }));

        dias.value = datos.dias;
        totales.value = datos.totales;
    } catch (e) {
        error.value = e.listaErrores?.[0] || e.message || 'No se pudieron cargar las reservaciones.';
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);
watch([vista, ancla], cargar);

function mover(pasos) {
    ancla.value = sumarDias(ancla.value, vista.value === 'dia' ? pasos : pasos * 7);
}

function irAHoy() {
    ancla.value = new Date(hoy);
}

const esHoy = (iso) => iso === aIsoFecha(hoy);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 space-y-3 border-b border-noche-700 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1">
                    <button type="button" class="btn-neutro h-10 min-h-0 w-10 px-0" aria-label="Anterior" @click="mover(-1)">
                        <Icono nombre="izquierda" clase="h-4 w-4" />
                    </button>
                    <button type="button" class="btn-neutro h-10 min-h-0 w-10 px-0" aria-label="Siguiente" @click="mover(1)">
                        <Icono nombre="derecha" clase="h-4 w-4" />
                    </button>
                    <button type="button" class="btn-fantasma h-10 min-h-0 px-3 text-sm" @click="irAHoy">Hoy</button>
                    <h1 class="ml-2 text-base font-bold text-slate-100 first-letter:uppercase">{{ etiquetaRango }}</h1>
                </div>

                <div class="flex gap-1 rounded-lg bg-noche-700 p-1">
                    <button
                        v-for="opcion in ['dia', 'semana']"
                        :key="opcion"
                        type="button"
                        class="rounded px-3 py-1.5 text-sm font-semibold capitalize transition"
                        :class="vista === opcion ? 'bg-slate-100 text-noche-900' : 'text-slate-400'"
                        @click="vista = opcion"
                    >
                        {{ opcion }}
                    </button>
                </div>
            </div>

            <div v-if="totales" class="flex flex-wrap gap-2 text-sm">
                <span class="chip bg-noche-700 text-slate-300">
                    {{ totales.reservaciones }} reservaciones
                </span>
                <span class="chip bg-acento-600/25 text-acento-400">
                    {{ totales.personas }} personas
                </span>
                <span class="chip bg-sky-600/20 text-sky-400">
                    <Icono nombre="globo" clase="h-3.5 w-3.5" />
                    {{ totales.desde_web }} en línea
                </span>
                <span class="chip bg-emerald-600/20 text-emerald-400">
                    <Icono nombre="tienda" clase="h-3.5 w-3.5" />
                    {{ totales.desde_pos }} mostrador
                </span>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
            <p v-if="cargando" class="py-12 text-center text-slate-500">Cargando calendario…</p>
            <p v-else-if="error" class="py-12 text-center text-sangre-400">{{ error }}</p>

            <div v-else class="grid gap-3" :class="vista === 'semana' ? 'lg:grid-cols-2 2xl:grid-cols-3' : ''">
                <section
                    v-for="dia in diasDelRango"
                    :key="dia.fecha"
                    class="tarjeta overflow-hidden"
                    :class="esHoy(dia.fecha) ? 'border-acento-500/60' : ''"
                >
                    <header class="flex items-center justify-between border-b border-noche-700 px-4 py-2.5">
                        <h2 class="text-sm font-bold text-slate-100 first-letter:uppercase">
                            {{ fechaLarga(dia.fecha) }}
                            <span v-if="esHoy(dia.fecha)" class="ml-1 text-xs font-semibold text-acento-400">hoy</span>
                        </h2>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <span v-if="dia.total_personas">{{ dia.total_personas }} pers.</span>
                            <span class="tabular">{{ dia.total_reservaciones }}</span>
                        </div>
                    </header>

                    <p v-if="!dia.reservaciones.length" class="px-4 py-6 text-center text-sm text-slate-600">
                        Sin reservaciones
                    </p>

                    <ul v-else class="divide-y divide-noche-700">
                        <li v-for="reserva in dia.reservaciones" :key="reserva.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-noche-700/50"
                                @click="seleccionada = reserva"
                            >
                                <span class="tabular w-16 shrink-0 text-sm font-bold text-slate-100">
                                    {{ horaCorta(reserva.fecha_hora) }}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-200">
                                        {{ reserva.producto }}
                                    </span>
                                    <span class="block truncate text-xs text-slate-500">
                                        {{ reserva.cliente || 'Sin nombre' }}
                                    </span>
                                </span>

                                <span class="chip shrink-0 bg-noche-700 text-slate-300">
                                    {{ reserva.personas }}
                                </span>

                                <span
                                    class="chip shrink-0"
                                    :class="reserva.origen === 'web'
                                        ? 'bg-sky-600/20 text-sky-400'
                                        : 'bg-emerald-600/20 text-emerald-400'"
                                    :title="reserva.origen === 'web' ? 'Reservada en el sitio web' : 'Reservada en mostrador'"
                                >
                                    <Icono :nombre="reserva.origen === 'web' ? 'globo' : 'tienda'" clase="h-3.5 w-3.5" />
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- Detalle -->
        <div
            v-if="seleccionada"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4"
            @click.self="seleccionada = null"
        >
            <div class="tarjeta w-full max-w-md rounded-b-none p-5 sm:rounded-b-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-100">{{ seleccionada.producto }}</h2>
                        <p class="text-sm text-slate-400 first-letter:uppercase">
                            {{ fechaLarga(seleccionada.fecha) }} · {{ horaCorta(seleccionada.fecha_hora) }}
                        </p>
                    </div>
                    <span
                        class="chip"
                        :class="seleccionada.origen === 'web'
                            ? 'bg-sky-600/20 text-sky-400'
                            : 'bg-emerald-600/20 text-emerald-400'"
                    >
                        <Icono :nombre="seleccionada.origen === 'web' ? 'globo' : 'tienda'" clase="h-3.5 w-3.5" />
                        {{ seleccionada.origen === 'web' ? 'Sitio web' : 'Mostrador' }}
                    </span>
                </div>

                <dl class="mt-4 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Cliente</dt>
                        <dd class="text-right font-medium text-slate-200">{{ seleccionada.cliente || '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Personas</dt>
                        <dd class="font-medium text-slate-200">{{ seleccionada.personas }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Estado</dt>
                        <dd class="font-medium capitalize text-slate-200">{{ seleccionada.estado }}</dd>
                    </div>

                    <template v-if="seleccionada.venta">
                        <div class="flex justify-between gap-3 border-t border-noche-700 pt-2.5">
                            <dt class="text-slate-500">Folio</dt>
                            <dd class="font-medium text-slate-200">{{ seleccionada.venta.folio }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Total</dt>
                            <dd class="tabular font-medium text-slate-200">{{ dinero(seleccionada.venta.total) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Pago</dt>
                            <dd class="font-medium capitalize text-slate-200">
                                {{ seleccionada.venta.formas_pago.join(', ') || 'Sin registro' }}
                            </dd>
                        </div>
                        <div v-if="seleccionada.venta.telefono" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Teléfono</dt>
                            <dd class="font-medium text-slate-200">
                                <a :href="`tel:${seleccionada.venta.telefono}`" class="underline">
                                    {{ seleccionada.venta.telefono }}
                                </a>
                            </dd>
                        </div>
                        <div v-if="seleccionada.venta.email" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Correo</dt>
                            <dd class="truncate font-medium text-slate-200">{{ seleccionada.venta.email }}</dd>
                        </div>
                        <div v-if="seleccionada.venta.estatus === 'cancelado'" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sangre-400">
                            La venta asociada está cancelada.
                        </div>
                    </template>
                </dl>

                <button type="button" class="btn-neutro mt-5 w-full" @click="seleccionada = null">Cerrar</button>
            </div>
        </div>
    </div>
</template>
