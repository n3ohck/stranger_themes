<script setup>
import { computed, ref } from 'vue';
import { useCarrito } from '../stores/carrito';
import { useCatalogo } from '../stores/catalogo';
import { dinero } from '../utils/formato';
import Icono from './Icono.vue';

const props = defineProps({
    linea: { type: Object, required: true },
});

const carrito = useCarrito();
const catalogo = useCatalogo();

const abierta = ref(true);
const errorDescuento = ref('');

/** Solo los códigos que aplican a este producto: el resto sobra en la lista. */
const descuentosAplicables = computed(() =>
    catalogo.descuentos.filter(
        (d) => !d.producto_tipo || d.producto_tipo === props.linea.producto.tipo
    )
);

const capacidad = computed(() => props.linea.producto.capacidad || 99);

function elegirDescuento(codigo) {
    errorDescuento.value = carrito.aplicarDescuento(props.linea.uid, codigo) || '';
}
</script>

<template>
    <div class="tarjeta overflow-hidden">
        <div class="flex items-start gap-3 p-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-100">{{ linea.producto.nombre }}</p>
                <p class="tabular mt-0.5 text-xs text-slate-500">
                    {{ dinero(linea.precio) }} por persona
                    <template v-if="linea.descuento">
                        · <span class="text-emerald-400">−{{ dinero(linea.descuentoUnitario) }} ({{ linea.descuento.porcentaje }}%)</span>
                    </template>
                </p>
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
                    {{ linea.cantidad }} {{ linea.cantidad === 1 ? 'persona' : 'personas' }}
                    <template v-if="linea.hora"> · {{ linea.horarios.find(h => h.inicio === linea.hora)?.etiqueta }}</template>
                    <template v-else-if="linea.producto.requiere_reservacion"> · sin horario</template>
                </span>
                <Icono :nombre="abierta ? 'menos' : 'mas'" clase="h-3.5 w-3.5" />
            </button>
        </div>

        <div v-if="abierta" class="space-y-3 border-t border-noche-700 bg-noche-900/40 p-3">
            <!-- Participantes: un solo control para toda la línea -->
            <div>
                <label class="etiqueta text-xs">Participantes</label>
                <div class="flex items-center gap-2">
                    <button
                        type="button" class="btn-neutro h-10 min-h-0 w-10 px-0"
                        :disabled="linea.cantidad <= 1"
                        aria-label="Quitar una persona"
                        @click="carrito.cambiarCantidad(linea.uid, linea.cantidad - 1)"
                    >
                        <Icono nombre="menos" clase="h-4 w-4" />
                    </button>
                    <span class="tabular w-10 text-center text-lg font-bold text-slate-100">{{ linea.cantidad }}</span>
                    <button
                        type="button" class="btn-neutro h-10 min-h-0 w-10 px-0"
                        :disabled="linea.cantidad >= capacidad"
                        aria-label="Agregar una persona"
                        @click="carrito.cambiarCantidad(linea.uid, linea.cantidad + 1)"
                    >
                        <Icono nombre="mas" clase="h-4 w-4" />
                    </button>
                    <span v-if="linea.producto.capacidad" class="text-xs text-slate-500">
                        máximo {{ linea.producto.capacidad }}
                    </span>
                </div>
            </div>

            <template v-if="linea.producto.requiere_reservacion">
                <!-- Un solo nombre, aunque el paquete traiga varios recorridos -->
                <div>
                    <label class="etiqueta text-xs">Nombre del cliente</label>
                    <input v-model="linea.cliente" type="text" class="campo py-2 text-sm" placeholder="Opcional">
                </div>

                <div>
                    <label class="etiqueta text-xs">Fecha</label>
                    <input
                        :value="linea.fecha" type="date" class="campo py-2 text-sm"
                        @change="carrito.cambiarFecha(linea.uid, $event.target.value)"
                    >
                </div>

                <div>
                    <label class="etiqueta text-xs">Horario</label>

                    <p v-if="linea.cargandoHorarios" class="text-xs text-slate-500">Buscando horarios…</p>
                    <p v-else-if="linea.avisoHorarios" class="text-xs text-amber-400">{{ linea.avisoHorarios }}</p>

                    <div v-else class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="h in linea.horarios" :key="h.inicio" type="button"
                            class="rounded border px-2 py-2 text-xs font-semibold transition"
                            :class="linea.hora === h.inicio
                                ? 'border-acento-500 bg-acento-500 text-white'
                                : h.libre
                                    ? 'border-noche-600 text-slate-200 hover:border-acento-500'
                                    : 'cursor-not-allowed border-noche-700 text-slate-600 line-through'"
                            :disabled="!h.libre"
                            :title="h.libre ? '' : 'Ese horario ya está ocupado'"
                            @click="carrito.elegirHorario(linea.uid, h.inicio)"
                        >{{ h.etiqueta }}</button>
                    </div>

                    <p v-if="linea.hora && linea.tramos.length > 1" class="mt-2 text-xs text-slate-500">
                        Los {{ linea.tramos.length }} recorridos van uno tras otro desde esa hora.
                    </p>
                </div>
            </template>

            <!-- Descuento: lista de códigos, no texto libre -->
            <div>
                <label class="etiqueta text-xs">Descuento</label>
                <select
                    :value="linea.codigo_descuento"
                    class="campo py-2 text-sm"
                    @change="elegirDescuento($event.target.value)"
                >
                    <option value="">Sin descuento</option>
                    <option v-for="d in descuentosAplicables" :key="d.id" :value="d.codigo">
                        {{ d.codigo }} — {{ d.porcentaje }}%
                    </option>
                </select>
                <p v-if="errorDescuento" class="mt-1 text-xs text-sangre-400">{{ errorDescuento }}</p>
                <p v-else-if="!descuentosAplicables.length" class="mt-1 text-xs text-slate-600">
                    No hay códigos que apliquen a este producto.
                </p>
            </div>
        </div>
    </div>
</template>
