<script setup>
import { useSesion } from '../stores/sesion';
import { dinero, fechaHora } from '../utils/formato';
import Icono from './Icono.vue';

defineProps({
    corte: { type: Object, required: true },
});

defineEmits(['cerrar']);

const sesion = useSesion();

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
                    <span class="font-semibold">Corte realizado</span>
                </div>
                <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5 text-sm" @click="$emit('cerrar')">
                    Cerrar
                </button>
            </div>

            <div class="min-h-0 overflow-y-auto rounded-lg bg-white p-3">
                <div id="ticket" class="mx-auto bg-white font-ticket text-[10px] leading-tight text-black" style="width: 48mm;">
                    <div class="text-center">
                        <p class="text-sm font-bold uppercase">Corte de Caja</p>
                        <p>{{ sesion.sucursal?.nombre }}</p>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <div class="flex justify-between"><span>Corte</span><span class="font-bold">#{{ corte.id }}</span></div>
                    <div class="flex justify-between"><span>Cajero</span><span>{{ sesion.usuario?.nombre }}</span></div>
                    <div class="flex justify-between"><span>Desde</span><span>{{ fechaHora(corte.fecha_inicio) }}</span></div>
                    <div class="flex justify-between"><span>Hasta</span><span>{{ fechaHora(corte.fecha_final) }}</span></div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>
                    <p class="font-bold uppercase">Ventas</p>

                    <div class="flex justify-between">
                        <span>Tickets</span><span>{{ corte.detalle?.ventas?.cantidad ?? '-' }}</span>
                    </div>
                    <div v-if="corte.detalle?.ventas?.canceladas" class="flex justify-between">
                        <span>Canceladas</span><span>{{ corte.detalle.ventas.canceladas }}</span>
                    </div>
                    <div v-if="corte.detalle?.ventas?.descuentos" class="flex justify-between">
                        <span>Descuentos</span><span>-{{ dinero(corte.detalle.ventas.descuentos) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between font-bold">
                        <span>Total vendido</span><span>{{ dinero(corte.total) }}</span>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>
                    <p class="font-bold uppercase">Formas de pago</p>

                    <div class="flex justify-between"><span>Efectivo</span><span>{{ dinero(corte.efectivo) }}</span></div>
                    <div class="flex justify-between"><span>Tarjeta</span><span>{{ dinero(corte.tarjeta) }}</span></div>
                    <div class="flex justify-between"><span>Transferencia</span><span>{{ dinero(corte.transferencia) }}</span></div>
                    <div v-if="corte.online > 0" class="flex justify-between"><span>En línea</span><span>{{ dinero(corte.online) }}</span></div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>
                    <p class="font-bold uppercase">Caja</p>

                    <div class="flex justify-between">
                        <span>Fondo inicial</span><span>{{ dinero(corte.detalle?.apertura?.fondo_inicial ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>+ Efectivo</span><span>{{ dinero(corte.efectivo) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>- Egresos</span><span>{{ dinero(corte.detalle?.salidas?.egresos_efectivo ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>- Pago empleados</span><span>{{ dinero(corte.detalle?.salidas?.pagos_empleados ?? 0) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between font-bold">
                        <span>Esperado</span><span>{{ dinero(corte.efectivo_esperado ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between font-bold">
                        <span>Contado</span><span>{{ dinero(corte.efectivo_contado) }}</span>
                    </div>
                    <div v-if="corte.diferencia !== null" class="flex justify-between font-bold">
                        <span>Diferencia</span>
                        <span>{{ corte.diferencia > 0 ? '+' : '' }}{{ dinero(corte.diferencia) }}</span>
                    </div>
                    <div v-if="corte.fondo_final > 0" class="flex justify-between">
                        <span>Fondo que queda</span><span>{{ dinero(corte.fondo_final) }}</span>
                    </div>

                    <p class="my-1.5 border-t border-dashed border-black"></p>

                    <p class="mt-6 text-center">_______________________</p>
                    <p class="text-center">Firma del cajero</p>
                    <p class="pb-4"></p>
                </div>
            </div>

            <div class="no-imprimir mt-3 flex gap-2">
                <button type="button" class="btn-neutro flex-1" @click="$emit('cerrar')">Cerrar</button>
                <button type="button" class="btn-primario flex-1" @click="imprimir">
                    <Icono nombre="imprimir" clase="h-5 w-5" />
                    Imprimir
                </button>
            </div>
        </div>
    </div>
</template>
