<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, conParametros } from '../api/cliente';
import { aFecha, aIsoFecha, sumarDias } from '../utils/formato';
import Icono from './Icono.vue';

/**
 * Calendario de tarjetas para elegir la fecha de un recorrido.
 *
 * Muestra dos semanas a la vez con el estado de cada día (cerrado, lleno o con
 * horarios libres) para que el cajero no tenga que abrir día por día hasta dar
 * con uno disponible. El estado viene de /pos-api/disponibilidad/dias, que usa
 * el mismo motor que la lista de horarios.
 */
const props = defineProps({
    modelValue: { type: String, required: true },
    productoId: { type: Number, required: true },
});

const emit = defineEmits(['update:modelValue']);

const DIAS_POR_PAGINA = 14;
// Hasta dónde se puede avanzar: más allá no se agenda en mostrador.
const PAGINAS_MAXIMAS = 13;

const hoy = aIsoFecha(new Date());
// Abre en la página que contiene la fecha elegida (p. ej. al duplicar una venta).
const pagina = ref(paginaDe(props.modelValue));
const dias = ref([]);
const cargando = ref(false);
const error = ref('');

const desde = computed(() => aIsoFecha(sumarDias(hoy, pagina.value * DIAS_POR_PAGINA)));

const titulo = computed(() => {
    const inicio = aFecha(desde.value);
    const fin = sumarDias(inicio, DIAS_POR_PAGINA - 1);
    const mes = (d) => d.toLocaleDateString('es-MX', { month: 'long' });

    return inicio.getMonth() === fin.getMonth()
        ? `${mes(inicio)} ${inicio.getFullYear()}`
        : `${mes(inicio)} – ${mes(fin)} ${fin.getFullYear()}`;
});

async function cargar() {
    cargando.value = true;
    error.value = '';

    try {
        const datos = await api.get(conParametros('disponibilidad/dias', {
            producto_id: props.productoId,
            desde: desde.value,
            dias: DIAS_POR_PAGINA,
        }));

        dias.value = datos.dias.map((dia) => {
            const fecha = aFecha(dia.fecha);

            return {
                ...dia,
                semana: fecha.toLocaleDateString('es-MX', { weekday: 'short' }).replace('.', ''),
                numero: fecha.getDate(),
                estado: !dia.abre ? 'cerrado' : dia.libres === 0 ? 'lleno' : dia.libres <= 2 ? 'pocos' : 'libre',
            };
        });
    } catch (e) {
        dias.value = [];
        error.value = e.listaErrores?.[0] || e.message || 'No se pudieron cargar las fechas.';
    } finally {
        cargando.value = false;
    }
}

const elegido = computed(() => dias.value.find((d) => d.fecha === props.modelValue));

const resumen = computed(() => {
    const dia = elegido.value;
    if (!dia) return '';

    const fecha = aFecha(dia.fecha).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });

    if (dia.estado === 'cerrado') return `${fecha} · la sucursal no abre`;
    if (dia.estado === 'lleno') return `${fecha} · sin horarios libres`;

    return `${fecha} · ${dia.libres} ${dia.libres === 1 ? 'horario libre' : 'horarios libres'}`;
});

function ayudaDe(dia) {
    if (dia.estado === 'cerrado') return 'La sucursal no abre ese día';
    if (dia.estado === 'lleno') return 'Ya no quedan horarios libres';

    return `${dia.libres} ${dia.libres === 1 ? 'horario libre' : 'horarios libres'}`;
}

function elegir(dia) {
    if (dia.estado === 'cerrado' || dia.estado === 'lleno') return;
    if (dia.fecha !== props.modelValue) emit('update:modelValue', dia.fecha);
}

function paginaDe(fecha) {
    const diferencia = Math.round((aFecha(fecha) - aFecha(hoy)) / 86400000);
    return Math.min(PAGINAS_MAXIMAS, Math.max(0, Math.floor(diferencia / DIAS_POR_PAGINA)));
}

/**
 * Una línea nueva arranca en hoy. Si hoy la sucursal no abre o ya no quedan
 * horarios, se brinca al primer día vendible para no hacer buscar al cajero.
 */
function saltarSiHoyNoSirve() {
    if (props.modelValue !== hoy) return;

    const dia = dias.value.find((d) => d.fecha === hoy);
    if (!dia || dia.estado === 'libre' || dia.estado === 'pocos') return;

    const siguiente = dias.value.find((d) => d.estado === 'libre' || d.estado === 'pocos');
    if (siguiente) emit('update:modelValue', siguiente.fecha);
}

onMounted(async () => {
    await cargar();
    saltarSiHoyNoSirve();
});

// La fecha puede cambiar desde fuera (otro recorrido la arrastra, o se duplica
// una venta): si cae en otra página, se va a esa página.
watch(() => props.modelValue, (fecha) => {
    const destino = paginaDe(fecha);
    if (destino !== pagina.value) pagina.value = destino;
});

watch(pagina, cargar);
watch(() => props.productoId, cargar);
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <button
                type="button" class="btn-fantasma h-8 min-h-0 w-8 px-0"
                :disabled="pagina === 0 || cargando"
                aria-label="Semanas anteriores"
                @click="pagina--"
            >
                <Icono nombre="izquierda" clase="h-4 w-4" />
            </button>
            <span class="text-xs font-semibold capitalize text-slate-300">{{ titulo }}</span>
            <button
                type="button" class="btn-fantasma h-8 min-h-0 w-8 px-0"
                :disabled="pagina >= PAGINAS_MAXIMAS || cargando"
                aria-label="Semanas siguientes"
                @click="pagina++"
            >
                <Icono nombre="derecha" clase="h-4 w-4" />
            </button>
        </div>

        <p v-if="error" class="text-xs text-sangre-400">{{ error }}</p>

        <div v-else class="grid grid-cols-7 gap-1" :class="{ 'opacity-50': cargando }">
            <template v-if="dias.length">
                <button
                    v-for="dia in dias" :key="dia.fecha" type="button"
                    class="flex flex-col items-center rounded border px-0.5 py-1.5 transition"
                    :class="dia.fecha === modelValue
                        ? 'border-acento-500 bg-acento-500 text-white'
                        : dia.estado === 'cerrado' || dia.estado === 'lleno'
                            ? 'cursor-not-allowed border-noche-700 text-slate-600'
                            : 'border-noche-600 text-slate-200 hover:border-acento-500'"
                    :disabled="dia.estado === 'cerrado' || dia.estado === 'lleno'"
                    :title="ayudaDe(dia)"
                    @click="elegir(dia)"
                >
                    <span class="text-[10px] uppercase leading-none"
                          :class="dia.fecha === modelValue ? 'text-white/80' : 'text-slate-500'">
                        {{ dia.fecha === hoy ? 'hoy' : dia.semana }}
                    </span>
                    <span class="tabular mt-1 text-base font-bold leading-none"
                          :class="{ 'line-through': dia.estado === 'cerrado' }">
                        {{ dia.numero }}
                    </span>
                    <span
                        class="mt-1.5 h-1.5 w-1.5 rounded-full"
                        :class="{
                            'bg-emerald-400': dia.estado === 'libre',
                            'bg-amber-400': dia.estado === 'pocos',
                            'bg-sangre-500': dia.estado === 'lleno',
                            'bg-transparent': dia.estado === 'cerrado',
                        }"
                    ></span>
                </button>
            </template>

            <!-- Esqueleto mientras llega la primera carga -->
            <span v-for="n in (dias.length ? 0 : DIAS_POR_PAGINA)" :key="`v${n}`"
                  class="h-[58px] animate-pulse rounded border border-noche-700 bg-noche-800"></span>
        </div>

        <p v-if="resumen" class="mt-2 text-xs first-letter:uppercase text-slate-400">{{ resumen }}</p>
        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-500">
            <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>libre</span>
            <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>pocos</span>
            <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-sangre-500"></span>lleno</span>
            <span class="flex items-center gap-1"><span class="line-through">00</span>cerrado</span>
        </p>
    </div>
</template>
