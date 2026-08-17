<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { dinero } from '../utils/formato';

const props = defineProps({
    total: { type: Number, required: true },
    guardando: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['cerrar', 'cobrar']);

/**
 * Un renglón por forma de pago. La mayoría de las ventas se cobran solo con
 * efectivo, así que ese campo arranca enfocado y con el total sugerido.
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

const cliente = reactive({ nombre: '', telefono: '', email: '' });

const num = (valor) => Number(valor) || 0;

const recibido = computed(() => num(pagos.efectivo) + num(pagos.tarjeta) + num(pagos.transferencia));
const faltante = computed(() => Math.max(0, props.total - recibido.value));
const cambio = computed(() => Math.max(0, recibido.value - props.total));
const puedeCobrar = computed(() => recibido.value + 0.001 >= props.total && props.total >= 0);

// Billetes con los que suele pagar la gente, calculados sobre el total.
const sugerencias = computed(() => {
    const base = props.total;
    const candidatos = [base, Math.ceil(base / 50) * 50, Math.ceil(base / 100) * 100, Math.ceil(base / 500) * 500];

    return [...new Set(candidatos)].filter((v) => v > 0).slice(0, 4);
});

watch(() => props.total, (valor) => {
    if (!pagos.tarjeta && !pagos.transferencia) {
        pagos.efectivo = valor ? String(valor) : '';
    }
}, { immediate: true });

function confirmar() {
    if (!puedeCobrar.value) return;

    emit('cobrar', {
        pagos: [
            { tipo: 'efectivo', monto: num(pagos.efectivo) },
            { tipo: 'tarjeta', monto: num(pagos.tarjeta), referencia: referencias.tarjeta },
            { tipo: 'transferencia', monto: num(pagos.transferencia), referencia: referencias.transferencia },
        ],
        cliente: { ...cliente },
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
                </div>

                <div>
                    <label class="etiqueta">Efectivo</label>
                    <input v-model="pagos.efectivo" type="number" inputmode="decimal" min="0" step="0.01"
                           class="campo tabular text-lg" placeholder="0.00" autofocus>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="sugerencia in sugerencias"
                            :key="sugerencia"
                            type="button"
                            class="btn-neutro min-h-0 px-3 py-1.5 text-sm"
                            @click="pagos.efectivo = String(sugerencia)"
                        >
                            {{ dinero(sugerencia) }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="etiqueta">Tarjeta</label>
                        <input v-model="pagos.tarjeta" type="number" inputmode="decimal" min="0" step="0.01"
                               class="campo tabular" placeholder="0.00">
                        <input v-if="Number(pagos.tarjeta) > 0" v-model="referencias.tarjeta" type="text"
                               class="campo mt-2 py-2 text-sm" placeholder="Referencia / autorización">
                    </div>
                    <div>
                        <label class="etiqueta">Transferencia</label>
                        <input v-model="pagos.transferencia" type="number" inputmode="decimal" min="0" step="0.01"
                               class="campo tabular" placeholder="0.00">
                        <input v-if="Number(pagos.transferencia) > 0" v-model="referencias.transferencia" type="text"
                               class="campo mt-2 py-2 text-sm" placeholder="Referencia">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 rounded-xl border border-noche-600 p-4">
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

                <details class="rounded-xl border border-noche-600">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-400">
                        Datos del cliente (opcional)
                    </summary>
                    <div class="grid gap-3 border-t border-noche-700 p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="etiqueta text-xs">Nombre</label>
                            <input v-model="cliente.nombre" type="text" class="campo py-2 text-sm">
                        </div>
                        <div>
                            <label class="etiqueta text-xs">Teléfono</label>
                            <input v-model="cliente.telefono" type="tel" class="campo py-2 text-sm">
                        </div>
                        <div>
                            <label class="etiqueta text-xs">Correo</label>
                            <input v-model="cliente.email" type="email" class="campo py-2 text-sm">
                        </div>
                    </div>
                </details>

                <p v-if="error" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">{{ error }}</p>
            </div>

            <div class="border-t border-noche-600 bg-noche-700/40 p-4">
                <button
                    type="button"
                    class="btn-primario w-full text-lg"
                    :disabled="!puedeCobrar || guardando"
                    @click="confirmar"
                >
                    {{ guardando ? 'Registrando…' : `Cobrar ${dinero(total)}` }}
                </button>
            </div>
        </div>
    </div>
</template>
