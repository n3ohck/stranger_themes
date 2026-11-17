<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api, dinero, fechaLarga } from '../api';

/**
 * Cuenta regresiva del horario apartado, para quien regresa de Stripe sin pagar.
 *
 * El tiempo restante lo da el servidor y aquí solo se descuenta con el reloj
 * monotónico del navegador: si el reloj del equipo está mal, la cuenta igual es
 * la correcta. Desde aquí se retoma el pago en la misma sesión de Stripe o se
 * suelta el horario para elegir otro.
 */
const props = defineProps({ referencia: { type: String, required: true } });
// vencido: se acabó el tiempo (el aviso sigue a la vista hasta que lo cierren).
// terminado: no hay nada que mostrar (pagado, liberado, cerrado o desconocido).
const emit = defineEmits(['liberado', 'vencido', 'terminado']);

const apartado = ref(null);
const restante = ref(0);
const liberando = ref(false);
const error = ref('');
const vencido = ref(false);

let base = 0;
let desde = 0;
let reloj = null;

const minutos = computed(() => String(Math.floor(restante.value / 60)).padStart(2, '0'));
const segundos = computed(() => String(restante.value % 60).padStart(2, '0'));
const urgente = computed(() => restante.value > 0 && restante.value <= 300);

const horario = computed(() => {
    const primero = apartado.value?.horarios?.[0];
    if (!primero) return '';

    return `${fechaLarga(primero.inicio)} · ${apartado.value.horarios.map((h) => hora(h.inicio)).join(', ')}`;
});

function hora(iso) {
    const [, h] = String(iso).split(' ');
    const [hh, mm] = h.split(':').map(Number);
    const d = new Date(); d.setHours(hh, mm, 0, 0);
    return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function tic() {
    restante.value = Math.max(0, Math.ceil(base - (performance.now() - desde) / 1000));

    if (restante.value === 0) {
        detener();
        marcarVencido();
    }
}

function marcarVencido() {
    vencido.value = true;
    apartado.value = null;
    emit('vencido');
}

function detener() {
    if (reloj) clearInterval(reloj);
    reloj = null;
}

async function cargar() {
    error.value = '';

    try {
        const datos = await api.apartado(props.referencia);

        // Venció mientras estaba en Stripe: se avisa. Pagado o liberado: nada que mostrar.
        if (!datos.vigente || datos.segundos_restantes <= 0) {
            if (['expirada', 'apartada'].includes(datos.estado)) marcarVencido();
            else emit('terminado', datos.estado);
            return;
        }

        apartado.value = datos;
        base = datos.segundos_restantes;
        desde = performance.now();
        vencido.value = false;
        detener();
        tic();
        reloj = setInterval(tic, 1000);
    } catch (e) {
        // 404: la referencia no existe (o es de otro navegador). Se olvida.
        if (e.estado === 404) emit('terminado', 'desconocido');
        else error.value = e.primero;
    }
}

async function liberar() {
    liberando.value = true;
    error.value = '';

    try {
        await api.liberar(props.referencia);
        detener();
        emit('liberado');
    } catch (e) {
        error.value = e.primero;
    } finally {
        liberando.value = false;
    }
}

// Al volver a la pestaña se resincroniza: algunos navegadores congelan los
// temporizadores en segundo plano.
function alVolver() {
    if (document.visibilityState === 'visible' && !vencido.value) cargar();
}

onMounted(() => {
    cargar();
    document.addEventListener('visibilitychange', alVolver);
});

onBeforeUnmount(() => {
    detener();
    document.removeEventListener('visibilitychange', alVolver);
});

watch(() => props.referencia, cargar);
</script>

<template>
    <div
        v-if="apartado || vencido || error"
        class="sticky top-0 z-20 mb-6 border bg-terror-fondo/95 p-4 backdrop-blur sm:p-5"
        :class="vencido ? 'border-terror-borde' : urgente ? 'border-terror-rojo' : 'border-terror-rojo/60'"
        role="status"
        aria-live="polite"
    >
        <template v-if="vencido">
            <p class="font-semibold text-white">Tu apartado venció</p>
            <p class="mt-1 text-sm">El horario se liberó. Puedes elegirlo de nuevo si sigue disponible.</p>
            <button type="button" class="mt-3 text-sm text-terror-rojoClaro underline" @click="emit('terminado', 'cerrado')">
                Entendido
            </button>
        </template>

        <template v-else-if="apartado">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="etiqueta-terror mb-1">Tu horario está apartado</p>
                    <p class="font-semibold text-white">{{ apartado.producto }}</p>
                    <p class="text-sm">{{ horario }} · {{ apartado.personas }} {{ apartado.personas === 1 ? 'persona' : 'personas' }} · {{ dinero(apartado.total) }}</p>
                </div>

                <div class="text-right">
                    <p
                        class="tabular font-marca text-4xl font-bold leading-none"
                        :class="urgente ? 'animate-pulse text-terror-rojo' : 'text-terror-rojoClaro'"
                        :aria-label="`Quedan ${minutos} minutos y ${segundos} segundos`"
                    >{{ minutos }}:{{ segundos }}</p>
                    <p class="mt-1 text-xs">para completar el pago</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <a v-if="apartado.url_pago" :href="apartado.url_pago" class="btn-rojo flex-1 text-center">
                    Continuar con el pago
                </a>
                <button
                    type="button"
                    class="flex-1 border border-terror-borde px-4 py-3 text-sm text-white transition hover:border-terror-rojo disabled:opacity-50"
                    :disabled="liberando"
                    @click="liberar"
                >
                    {{ liberando ? 'Liberando…' : 'Liberar y elegir otro horario' }}
                </button>
            </div>
            <p v-if="!apartado.url_pago" class="mt-3 text-xs">
                El enlace de pago ya no está disponible. Libera el horario para empezar otra compra.
            </p>
        </template>

        <p v-if="error" class="mt-3 text-sm text-terror-rojoClaro">{{ error }}</p>
    </div>
</template>
