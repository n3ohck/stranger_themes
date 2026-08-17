<script setup>
import { ref } from 'vue';
import { useCarrito } from '../stores/carrito';
import { dinero } from '../utils/formato';
import Icono from './Icono.vue';

const props = defineProps({
    linea: { type: Object, required: true },
});

const carrito = useCarrito();

const codigo = ref(props.linea.codigo_descuento || '');
const errorDescuento = ref('');
const abierta = ref(props.linea.producto.requiere_reservacion);

function aplicar() {
    errorDescuento.value = carrito.aplicarDescuento(props.linea.uid, codigo.value.trim()) || '';
}

function limpiarDescuento() {
    codigo.value = '';
    errorDescuento.value = '';
    carrito.aplicarDescuento(props.linea.uid, '');
}

/**
 * Marcar personas como editadas evita que cambiar la cantidad de la línea
 * pise un número de personas que el cajero ya ajustó a mano.
 */
function personasEditadas(reservacion) {
    reservacion.personasEditadas = true;
}
</script>

<template>
    <div class="tarjeta overflow-hidden">
        <div class="flex items-start gap-3 p-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-100">{{ linea.producto.nombre }}</p>
                <p class="tabular mt-0.5 text-xs text-slate-500">
                    {{ dinero(linea.precio) }} c/u
                    <template v-if="linea.descuento">
                        · <span class="text-emerald-400">−{{ dinero(linea.descuentoUnitario) }} ({{ linea.descuento.porcentaje }}%)</span>
                    </template>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    class="btn-neutro h-9 min-h-0 w-9 px-0"
                    :disabled="linea.cantidad <= 1"
                    aria-label="Quitar uno"
                    @click="carrito.cambiarCantidad(linea.uid, linea.cantidad - 1)"
                >
                    <Icono nombre="menos" clase="h-4 w-4" />
                </button>
                <span class="tabular w-8 text-center text-base font-bold text-slate-100">{{ linea.cantidad }}</span>
                <button
                    type="button"
                    class="btn-neutro h-9 min-h-0 w-9 px-0"
                    aria-label="Agregar uno"
                    @click="carrito.cambiarCantidad(linea.uid, linea.cantidad + 1)"
                >
                    <Icono nombre="mas" clase="h-4 w-4" />
                </button>
            </div>

            <div class="shrink-0 text-right">
                <p class="tabular text-base font-bold text-slate-100">{{ dinero(linea.total) }}</p>
                <p v-if="linea.descuento" class="tabular text-xs text-slate-500 line-through">
                    {{ dinero(linea.subtotal) }}
                </p>
            </div>

            <button
                type="button"
                class="btn-fantasma h-9 min-h-0 w-9 shrink-0 px-0 text-slate-500 hover:text-sangre-400"
                aria-label="Eliminar línea"
                @click="carrito.quitar(linea.uid)"
            >
                <Icono nombre="basura" clase="h-4 w-4" />
            </button>
        </div>

        <div class="border-t border-noche-700 px-3 py-2">
            <button
                type="button"
                class="flex w-full items-center justify-between text-xs font-medium text-slate-400 hover:text-slate-200"
                @click="abierta = !abierta"
            >
                <span>
                    {{ linea.producto.requiere_reservacion
                        ? `Reservación (${linea.reservaciones.length})`
                        : 'Descuento' }}
                </span>
                <Icono :nombre="abierta ? 'menos' : 'mas'" clase="h-3.5 w-3.5" />
            </button>
        </div>

        <div v-if="abierta" class="space-y-3 border-t border-noche-700 bg-noche-900/40 p-3">
            <div
                v-for="(reservacion, indice) in linea.reservaciones"
                :key="indice"
                class="rounded-lg border border-noche-600 p-2.5"
            >
                <p class="mb-2 text-xs font-semibold text-acento-400">
                    {{ reservacion.etiqueta }}
                </p>

                <div class="grid grid-cols-2 gap-2">
                    <div class="col-span-2">
                        <label class="etiqueta text-xs">Nombre del cliente</label>
                        <input
                            v-model="reservacion.nombre"
                            type="text"
                            class="campo py-2 text-sm"
                            placeholder="Opcional"
                        >
                    </div>
                    <div>
                        <label class="etiqueta text-xs">Fecha</label>
                        <input v-model="reservacion.fecha" type="date" class="campo py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="etiqueta text-xs">Hora</label>
                        <input v-model="reservacion.hora" type="time" class="campo py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="etiqueta text-xs">Personas</label>
                        <input
                            v-model.number="reservacion.personas"
                            type="number"
                            min="1"
                            class="campo py-2 text-sm"
                            @input="personasEditadas(reservacion)"
                        >
                    </div>
                </div>
            </div>

            <div>
                <label class="etiqueta text-xs">Código de descuento</label>
                <div class="flex gap-2">
                    <input
                        v-model="codigo"
                        type="text"
                        class="campo py-2 text-sm uppercase"
                        placeholder="Sin descuento"
                        @keyup.enter="aplicar"
                    >
                    <button v-if="linea.descuento" type="button" class="btn-fantasma min-h-0 px-3 py-2 text-sm" @click="limpiarDescuento">
                        Quitar
                    </button>
                    <button v-else type="button" class="btn-neutro min-h-0 px-3 py-2 text-sm" @click="aplicar">
                        Aplicar
                    </button>
                </div>
                <p v-if="errorDescuento" class="mt-1 text-xs text-sangre-400">{{ errorDescuento }}</p>
                <p v-else-if="linea.descuento" class="mt-1 text-xs text-emerald-400">
                    {{ linea.descuento.codigo }} · {{ linea.descuento.porcentaje }}% aplicado
                </p>
            </div>
        </div>
    </div>
</template>
