<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/cliente';
import { useSesion } from '../stores/sesion';
import { dinero, fechaHora } from '../utils/formato';
import TicketCorte from '../components/TicketCorte.vue';
import Icono from '../components/Icono.vue';

const sesion = useSesion();
const router = useRouter();

const precorte = ref(null);
const cargando = ref(true);
const error = ref('');

const efectivoContado = ref('');
const fondoFinal = ref('');
const confirmando = ref(false);
const enviando = ref(false);
const errorCierre = ref('');
const corteHecho = ref(null);

const contado = computed(() => Number(efectivoContado.value) || 0);
const esperado = computed(() => precorte.value?.caja.efectivo_esperado ?? 0);
const diferencia = computed(() => contado.value - esperado.value);
const hayDiferencia = computed(() => Math.abs(diferencia.value) >= 0.01);

async function cargar() {
    cargando.value = true;
    error.value = '';

    try {
        const datos = await api.get('caja/precorte');
        precorte.value = datos.precorte;
    } catch (e) {
        error.value = e.message || 'No se pudo calcular el corte.';
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);

async function cerrar() {
    errorCierre.value = '';
    enviando.value = true;

    try {
        const datos = await api.post('caja/corte', {
            efectivo_contado: contado.value,
            fondo_final: Number(fondoFinal.value) || 0,
        });

        confirmando.value = false;
        corteHecho.value = datos.corte;
        await sesion.refrescar();
    } catch (e) {
        errorCierre.value = e.listaErrores?.[0] || e.message || 'No se pudo cerrar la caja.';
    } finally {
        enviando.value = false;
    }
}

function terminar() {
    corteHecho.value = null;
    router.push({ name: 'apertura' });
}
</script>

<template>
    <div class="h-full min-h-0 overflow-y-auto">
        <div class="mx-auto max-w-3xl p-3 sm:p-6">
            <div class="mb-4">
                <h1 class="text-2xl font-bold text-slate-100">Corte de Caja</h1>
                <p v-if="precorte" class="text-sm text-slate-400">
                    Turno abierto {{ fechaHora(precorte.apertura.abierta_en) }} · fondo inicial
                    {{ dinero(precorte.apertura.fondo_inicial) }}
                </p>
            </div>

            <p v-if="cargando" class="py-12 text-center text-slate-500">Calculando…</p>
            <p v-else-if="error" class="rounded-lg bg-sangre-600/15 px-4 py-3 text-sangre-400">{{ error }}</p>

            <div v-else-if="precorte" class="space-y-4">
                <!-- Ventas -->
                <section class="tarjeta p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Resumen de ventas</h2>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div>
                            <p class="text-xs text-slate-500">Total vendido</p>
                            <p class="tabular text-xl font-bold text-slate-100">{{ dinero(precorte.ventas.total) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Efectivo</p>
                            <p class="tabular text-xl font-bold text-emerald-400">{{ dinero(precorte.ventas.efectivo) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Tarjeta</p>
                            <p class="tabular text-xl font-bold text-slate-100">{{ dinero(precorte.ventas.tarjeta) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Transferencia</p>
                            <p class="tabular text-xl font-bold text-slate-100">{{ dinero(precorte.ventas.transferencia) }}</p>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-1.5 border-t border-noche-700 pt-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Tickets del turno</dt>
                            <dd class="tabular text-slate-200">{{ precorte.ventas.cantidad }}</dd>
                        </div>
                        <div v-if="precorte.ventas.canceladas" class="flex justify-between">
                            <dt class="text-slate-500">Canceladas</dt>
                            <dd class="tabular text-sangre-400">{{ precorte.ventas.canceladas }}</dd>
                        </div>
                        <div v-if="precorte.ventas.descuentos" class="flex justify-between">
                            <dt class="text-slate-500">Descuentos otorgados</dt>
                            <dd class="tabular text-emerald-400">−{{ dinero(precorte.ventas.descuentos) }}</dd>
                        </div>
                        <div v-if="precorte.ventas.online" class="flex justify-between">
                            <dt class="text-slate-500">Ventas en línea del periodo</dt>
                            <dd class="tabular text-sky-400">{{ dinero(precorte.ventas.online) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Arqueo -->
                <section class="tarjeta p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Arqueo de efectivo</h2>

                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Fondo inicial</dt>
                            <dd class="tabular text-slate-200">{{ dinero(precorte.apertura.fondo_inicial) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">+ Efectivo de ventas</dt>
                            <dd class="tabular text-slate-200">{{ dinero(precorte.ventas.efectivo) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">− Egresos en efectivo</dt>
                            <dd class="tabular text-slate-200">{{ dinero(precorte.salidas.egresos_efectivo) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">− Pagos a empleados</dt>
                            <dd class="tabular text-slate-200">{{ dinero(precorte.salidas.pagos_empleados) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-noche-700 pt-2 text-base font-bold">
                            <dt class="text-slate-300">Debe haber en caja</dt>
                            <dd class="tabular text-slate-100">{{ dinero(precorte.caja.efectivo_esperado) }}</dd>
                        </div>
                    </dl>

                    <p class="mt-2 text-xs text-slate-600">
                        El cambio entregado ({{ dinero(precorte.ventas.cambio_entregado) }}) ya está descontado del
                        efectivo de ventas.
                        <RouterLink :to="{ name: 'movimientos' }" class="text-slate-400 underline hover:text-slate-200">
                            Ver o registrar egresos y pagos
                        </RouterLink>.
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contado" class="etiqueta">Efectivo contado en caja</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-500">$</span>
                                <input id="contado" v-model="efectivoContado" type="number" inputmode="decimal"
                                       min="0" step="0.01" class="campo tabular pl-8 text-lg font-semibold" placeholder="0.00">
                            </div>
                        </div>
                        <div>
                            <label for="fondo" class="etiqueta">Fondo que queda para el siguiente turno</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-500">$</span>
                                <input id="fondo" v-model="fondoFinal" type="number" inputmode="decimal"
                                       min="0" step="0.01" class="campo tabular pl-8 text-lg font-semibold" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="efectivoContado !== ''"
                        class="mt-4 flex items-center justify-between rounded-lg px-4 py-3"
                        :class="hayDiferencia ? 'bg-amber-600/15' : 'bg-emerald-600/15'"
                    >
                        <span class="flex items-center gap-2 text-sm font-semibold"
                              :class="hayDiferencia ? 'text-amber-400' : 'text-emerald-400'">
                            <Icono :nombre="hayDiferencia ? 'alerta' : 'check'" clase="h-5 w-5" />
                            {{ hayDiferencia ? (diferencia > 0 ? 'Sobrante' : 'Faltante') : 'La caja cuadra' }}
                        </span>
                        <span class="tabular text-lg font-bold"
                              :class="hayDiferencia ? 'text-amber-400' : 'text-emerald-400'">
                            {{ diferencia > 0 ? '+' : '' }}{{ dinero(diferencia) }}
                        </span>
                    </div>
                </section>

                <button
                    type="button"
                    class="btn-primario w-full text-lg"
                    :disabled="efectivoContado === ''"
                    @click="confirmando = true"
                >
                    Realizar corte y cerrar caja
                </button>

                <p class="pb-4 text-center text-xs text-slate-600">
                    Al cerrar la caja termina tu turno y tendrás que hacer una nueva apertura para seguir vendiendo.
                </p>
            </div>
        </div>

        <!-- Confirmación -->
        <div v-if="confirmando" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="tarjeta w-full max-w-md p-5">
                <h2 class="text-lg font-bold text-slate-100">Confirmar corte</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Se cerrará el turno con {{ dinero(contado) }} contados en caja.
                </p>

                <p v-if="hayDiferencia" class="mt-3 flex items-start gap-2 rounded-lg bg-amber-600/15 px-3 py-2 text-sm text-amber-400">
                    <Icono nombre="alerta" clase="h-5 w-5 shrink-0" />
                    <span>
                        Hay un {{ diferencia > 0 ? 'sobrante' : 'faltante' }} de
                        {{ dinero(Math.abs(diferencia)) }}. Quedará registrado en el corte.
                    </span>
                </p>

                <p v-if="errorCierre" class="mt-3 rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">
                    {{ errorCierre }}
                </p>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="btn-neutro flex-1" @click="confirmando = false">Volver</button>
                    <button type="button" class="btn-primario flex-1" :disabled="enviando" @click="cerrar">
                        {{ enviando ? 'Cerrando…' : 'Cerrar caja' }}
                    </button>
                </div>
            </div>
        </div>

        <TicketCorte v-if="corteHecho" :corte="corteHecho" @cerrar="terminar" />
    </div>
</template>
