<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { api } from '../api/cliente';
import { useSesion } from '../stores/sesion';
import { dinero, fechaHora, montoConLetra } from '../utils/formato';
import CampoComprobante from './CampoComprobante.vue';
import Icono from './Icono.vue';

/**
 * Recibo que el empleado firma al recibir el dinero. El flujo es registrar el
 * pago, imprimir este ticket, que el empleado lo firme y subir la foto del
 * ticket firmado como comprobante. Recién registrado se imprime solo; desde la
 * lista se vuelve a abrir para reimprimir o para subir la foto pendiente.
 */
const props = defineProps({
    pago: { type: Object, required: true },
    autoImprimir: { type: Boolean, default: false },
});

const emit = defineEmits(['cerrar', 'guardado']);

const sesion = useSesion();

const foto = ref(null);
const guardando = ref(false);
const error = ref('');

function imprimir() {
    window.print();
}

onMounted(async () => {
    if (!props.autoImprimir) return;

    // Esperar a que el ticket esté pintado: imprimir antes deja la hoja en blanco.
    await nextTick();
    requestAnimationFrame(imprimir);
});

async function guardarFoto() {
    error.value = '';
    guardando.value = true;

    try {
        const respuesta = await api.subir(`empleados/pagos/${props.pago.id}/comprobante`, {
            // El backend espera el archivo bajo 'imagen': el mutator del modelo
            // lo lee del request por ese nombre exacto.
            imagen: foto.value,
        });

        emit('guardado', respuesta.pago);
    } catch (e) {
        error.value = e.listaErrores?.[0] || e.message || 'No se pudo guardar el ticket firmado.';
    } finally {
        guardando.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="flex max-h-full w-full max-w-sm flex-col">
            <div class="no-imprimir mb-3 flex items-center justify-between">
                <div v-if="pago.comprobante" class="flex items-center gap-2 text-emerald-400">
                    <Icono nombre="check" />
                    <span class="font-semibold">Ticket firmado</span>
                </div>
                <div v-else class="flex items-center gap-2 text-amber-400">
                    <Icono nombre="documento" />
                    <span class="font-semibold">Falta el ticket firmado</span>
                </div>
                <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5 text-sm" @click="$emit('cerrar')">
                    Cerrar
                </button>
            </div>

            <div class="min-h-0 overflow-y-auto rounded-lg bg-white p-3">
                <div id="ticket" class="mx-auto bg-white font-ticket text-[10px] leading-tight text-black" style="width: 48mm;">
                    <div class="text-center">
                        <p class="text-sm font-bold uppercase">Pago a empleado</p>
                        <p>{{ sesion.sucursal?.nombre }}</p>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <div class="flex justify-between"><span>Folio</span><span class="font-bold">#{{ pago.id }}</span></div>
                    <div class="flex justify-between"><span>Fecha</span><span>{{ fechaHora(pago.fecha) }}</span></div>
                    <div class="flex justify-between"><span>Entregó</span><span>{{ sesion.usuario?.nombre }}</span></div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <p>Recibí la cantidad de:</p>
                    <p class="my-1 text-center text-base font-bold">{{ dinero(pago.monto) }}</p>
                    <p class="text-center">({{ montoConLetra(pago.monto) }})</p>
                    <p class="mt-1">en efectivo, a mi entera satisfacción.</p>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <p class="mt-10 text-center">_______________________</p>
                    <p class="text-center font-bold">{{ pago.empleado }}</p>
                    <p class="text-center">Firma de recibido</p>
                    <p class="pb-4"></p>
                </div>
            </div>

            <form class="no-imprimir tarjeta mt-3 space-y-3 p-3" novalidate @submit.prevent="guardarFoto">
                <a v-if="pago.comprobante && !foto" :href="pago.comprobante" target="_blank" rel="noopener"
                   class="flex items-center gap-3 text-sm text-slate-300" title="Ver ticket firmado">
                    <img :src="pago.comprobante" alt="" class="h-12 w-12 rounded object-cover">
                    Ya tiene foto. Puedes reemplazarla si salió mal.
                </a>

                <CampoComprobante v-model="foto" requerido etiqueta="Foto del ticket firmado" />

                <p v-if="error" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">{{ error }}</p>

                <div class="flex gap-2">
                    <button type="button" class="btn-neutro flex-1" @click="imprimir">
                        <Icono nombre="imprimir" clase="h-5 w-5" />
                        Reimprimir
                    </button>
                    <button type="submit" class="btn-primario flex-1" :disabled="guardando || !foto">
                        {{ guardando ? 'Guardando…' : 'Guardar foto' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
