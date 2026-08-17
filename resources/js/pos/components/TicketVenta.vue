<script setup>
import { computed } from 'vue';
import { useSesion } from '../stores/sesion';
import { dinero, fechaHora, horaCorta, fechaCorta } from '../utils/formato';
import Icono from './Icono.vue';

const props = defineProps({
    venta: { type: Object, required: true },
});

defineEmits(['cerrar']);

const sesion = useSesion();

const etiquetaPago = {
    efectivo: 'Efectivo',
    tarjeta: 'Tarjeta',
    transferencia: 'Transfer.',
    online: 'En línea',
};

const subtotal = computed(() => props.venta.total + props.venta.descuento);
const cambio = computed(() => props.venta.pagos.reduce((suma, p) => suma + (p.cambio || 0), 0));

function imprimir() {
    window.print();
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="flex max-h-full w-full max-w-sm flex-col">
            <div class="no-imprimir mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2 text-emerald-400">
                    <Icono nombre="check" />
                    <span class="font-semibold">Venta registrada</span>
                </div>
                <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5 text-sm" @click="$emit('cerrar')">
                    Cerrar
                </button>
            </div>

            <!--
                El ancho de 48mm y la tipografía monoespaciada replican en pantalla
                lo que sale del rollo, para que el cajero note un texto cortado
                antes de gastar papel.
            -->
            <div class="min-h-0 overflow-y-auto rounded-lg bg-white p-3">
                <div id="ticket" class="mx-auto bg-white font-ticket text-[10px] leading-tight text-black" style="width: 48mm;">
                    <div class="text-center">
                        <p class="text-sm font-bold uppercase">{{ venta.sucursal?.nombre || sesion.sucursal?.nombre }}</p>
                        <p v-if="venta.sucursal?.direccion" class="mt-0.5">{{ venta.sucursal.direccion }}</p>
                        <p v-if="venta.sucursal?.telefono">Tel. {{ venta.sucursal.telefono }}</p>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <div class="flex justify-between"><span>Folio</span><span class="font-bold">{{ venta.folio }}</span></div>
                    <div class="flex justify-between"><span>Fecha</span><span>{{ fechaHora(venta.fecha) }}</span></div>
                    <div class="flex justify-between"><span>Atendió</span><span>{{ sesion.usuario?.nombre }}</span></div>
                    <div v-if="venta.cliente" class="flex justify-between"><span>Cliente</span><span>{{ venta.cliente }}</span></div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <div v-for="(linea, i) in venta.lineas" :key="i" class="mb-1">
                        <p class="font-bold uppercase">{{ linea.producto }}</p>
                        <div class="flex justify-between">
                            <span>{{ linea.cantidad }} x {{ dinero(linea.precio) }}</span>
                            <span>{{ dinero(linea.cantidad * linea.precio) }}</span>
                        </div>
                        <div v-if="linea.descuento > 0" class="flex justify-between">
                            <span>  Desc.</span>
                            <span>-{{ dinero(linea.descuento) }}</span>
                        </div>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <div class="flex justify-between"><span>Subtotal</span><span>{{ dinero(subtotal) }}</span></div>
                    <div v-if="venta.descuento > 0" class="flex justify-between">
                        <span>Descuento</span><span>-{{ dinero(venta.descuento) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between text-sm font-bold">
                        <span>TOTAL</span><span>{{ dinero(venta.total) }}</span>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <div v-for="(pago, i) in venta.pagos" :key="`p${i}`" class="flex justify-between">
                        <span>{{ etiquetaPago[pago.tipo] || pago.tipo }}</span>
                        <span>{{ dinero(pago.monto) }}</span>
                    </div>
                    <div v-if="cambio > 0" class="flex justify-between font-bold">
                        <span>Cambio</span><span>{{ dinero(cambio) }}</span>
                    </div>

                    <template v-if="venta.reservaciones?.length">
                        <p class="my-1.5 border-t border-dashed border-black"></p>
                        <p class="text-center font-bold uppercase">Reservaciones</p>

                        <div v-for="(reservacion, r) in venta.reservaciones" :key="`r${r}`" class="mt-1">
                            <p class="font-bold uppercase">{{ reservacion.producto }}</p>
                            <div class="flex justify-between">
                                <span>{{ fechaCorta(reservacion.fecha) }} {{ horaCorta(reservacion.fecha) }}</span>
                                <span>{{ reservacion.personas }} pers.</span>
                            </div>
                            <p v-if="reservacion.nombre">A nombre de: {{ reservacion.nombre }}</p>
                        </div>

                        <p class="mt-1.5 text-center">Presenta este ticket al llegar.<br>Llega 15 min antes.</p>
                    </template>

                    <p class="my-1.5 border-t border-dashed border-black"></p>
                    <p class="text-center">¡Gracias por tu visita!</p>
                    <p class="pb-4 text-center">{{ venta.folio }}</p>
                </div>
            </div>

            <div class="no-imprimir mt-3 flex gap-2">
                <button type="button" class="btn-neutro flex-1" @click="$emit('cerrar')">Nueva venta</button>
                <button type="button" class="btn-primario flex-1" @click="imprimir">
                    <Icono nombre="imprimir" clase="h-5 w-5" />
                    Imprimir
                </button>
            </div>
        </div>
    </div>
</template>
