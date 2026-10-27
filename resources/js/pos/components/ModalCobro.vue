<script setup>
import { computed, reactive } from 'vue';
import { dinero } from '../utils/formato';

const props = defineProps({
    total: { type: Number, required: true },
    guardando: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['cerrar', 'cobrar']);

/**
 * Un renglón por forma de pago. Todos arrancan vacíos: si el efectivo traía el
 * total sugerido y el cliente pagaba con tarjeta, el dinero se contaba dos veces
 * y salía un cambio que no existía hasta borrarlo a mano.
 */
const pagos = reactive({
    efectivo: '',
    tarjeta: '',
    transferencia: '',
});

const referencias = reactive({
    tarjeta: '',
    transferencia: '',
});

const num = (valor) => Number(valor) || 0;

const recibido = computed(() => num(pagos.efectivo) + num(pagos.tarjeta) + num(pagos.transferencia));
const faltante = computed(() => Math.max(0, props.total - recibido.value));
const cambio = computed(() => Math.max(0, recibido.value - props.total));
const puedeCobrar = computed(() => recibido.value + 0.001 >= props.total && props.total >= 0);

// Los billetes que circulan, siempre los mismos sin importar qué se cobre. Se
// suman a lo capturado para cubrir pagos con varios (500 + 200).
const BILLETES = [20, 50, 100, 200, 500];

function sumarBillete(billete) {
    pagos.efectivo = String(num(pagos.efectivo) + billete);
}

/** Lo que falta por cubrir sin contar lo ya capturado en esa forma de pago. */
function restanteSin(forma) {
    return Math.max(0, props.total - (recibido.value - num(pagos[forma])));
}

function completar(forma) {
    const monto = restanteSin(forma);
    pagos[forma] = monto ? String(Math.round(monto * 100) / 100) : '';
}

function confirmar() {
    if (!puedeCobrar.value) return;

    emit('cobrar', {
        pagos: [
            { tipo: 'efectivo', monto: num(pagos.efectivo) },
            { tipo: 'tarjeta', monto: num(pagos.tarjeta), referencia: referencias.tarjeta },
            { tipo: 'transferencia', monto: num(pagos.transferencia), referencia: referencias.transferencia },
        ],
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4">
        <div class="tarjeta flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-b-none sm:rounded-b-xl">
            <div class="flex items-center justify-between border-b border-noche-600 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-100">Cobrar</h2>
                <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5" @click="emit('cerrar')">Cancelar</button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <div class="rounded-xl bg-noche-900 p-4 text-center">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total a cobrar</p>
                    <p class="tabular mt-1 text-4xl font-extrabold text-slate-100">{{ dinero(total) }}</p>
                    <p v-if="total <= 0" class="mt-2 text-sm text-emerald-400">
                        Cortesía: no hay nada que cobrar.
                    </p>
                </div>

                <div v-if="total > 0">
                    <div class="flex items-center justify-between">
                        <label class="etiqueta">Efectivo</label>
                        <button v-if="pagos.efectivo" type="button" class="mb-1.5 text-xs text-slate-500 hover:text-slate-300"
                                @click="pagos.efectivo = ''">Limpiar</button>
                    </div>
                    <input v-model="pagos.efectivo" type="number" inputmode="decimal" min="0" step="0.01"
                           class="campo tabular text-lg" placeholder="0.00" autofocus>
                    <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-6">
                        <button
                            type="button"
                            class="btn-neutro min-h-0 px-2 py-2 text-sm"
                            :disabled="!restanteSin('efectivo')"
                            title="Lo que falta, en efectivo y sin cambio"
                            @click="completar('efectivo')"
                        >
                            Exacto
                        </button>
                        <button
                            v-for="billete in BILLETES"
                            :key="billete"
                            type="button"
                            class="btn-neutro tabular min-h-0 px-2 py-2 text-sm"
                            :title="`Sumar un billete de ${dinero(billete)}`"
                            @click="sumarBillete(billete)"
                        >
                            +{{ billete }}
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Los billetes se suman: 500 y 200 son $700 recibidos.</p>
                </div>

                <div v-if="total > 0" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="etiqueta">Tarjeta</label>
                            <button v-if="restanteSin('tarjeta') && !pagos.tarjeta" type="button"
                                    class="mb-1.5 text-xs font-semibold text-acento-400 hover:text-acento-500"
                                    @click="completar('tarjeta')">Completar</button>
                        </div>
                        <input v-model="pagos.tarjeta" type="number" inputmode="decimal" min="0" step="0.01"
                               class="campo tabular" placeholder="0.00">
                        <input v-if="Number(pagos.tarjeta) > 0" v-model="referencias.tarjeta" type="text"
                               class="campo mt-2 py-2 text-sm" placeholder="Referencia / autorización">
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="etiqueta">Transferencia</label>
                            <button v-if="restanteSin('transferencia') && !pagos.transferencia" type="button"
                                    class="mb-1.5 text-xs font-semibold text-acento-400 hover:text-acento-500"
                                    @click="completar('transferencia')">Completar</button>
                        </div>
                        <input v-model="pagos.transferencia" type="number" inputmode="decimal" min="0" step="0.01"
                               class="campo tabular" placeholder="0.00">
                        <input v-if="Number(pagos.transferencia) > 0" v-model="referencias.transferencia" type="text"
                               class="campo mt-2 py-2 text-sm" placeholder="Referencia">
                    </div>
                </div>

                <div v-if="total > 0" class="grid grid-cols-2 gap-3 rounded-xl border border-noche-600 p-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Recibido</p>
                        <p class="tabular text-xl font-bold text-slate-100">{{ dinero(recibido) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-slate-500">
                            {{ faltante > 0 ? 'Falta' : 'Cambio' }}
                        </p>
                        <p class="tabular text-xl font-bold" :class="faltante > 0 ? 'text-sangre-400' : 'text-emerald-400'">
                            {{ dinero(faltante > 0 ? faltante : cambio) }}
                        </p>
                    </div>
                </div>


                <p v-if="error" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">{{ error }}</p>
            </div>

            <div class="border-t border-noche-600 bg-noche-700/40 p-4">
                <button
                    type="button"
                    class="btn-primario w-full text-lg"
                    :disabled="!puedeCobrar || guardando"
                    @click="confirmar"
                >
                    {{ guardando ? 'Registrando…' : (total > 0 ? `Cobrar ${dinero(total)}` : 'Registrar venta') }}
                </button>
            </div>
        </div>
    </div>
</template>
