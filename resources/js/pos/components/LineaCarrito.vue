<script setup>
import { computed, ref } from 'vue';
import { chocaConOtro, useCarrito } from '../stores/carrito';
import { useCatalogo } from '../stores/catalogo';
import { dinero, fechaCorta } from '../utils/formato';
import Icono from './Icono.vue';
import SelectorFecha from './SelectorFecha.vue';

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

const capacidad = computed(() => props.linea.producto.capacidad || null);

function elegirDescuento(codigo) {
    errorDescuento.value = carrito.aplicarDescuento(props.linea.uid, codigo) || '';
}

// Paquete: cada recorrido elige su fecha y hora. Se muestra abierto uno a la vez
// (el primero sin horario, o el que el cajero toque) para no apilar calendarios.
const varios = computed(() => props.linea.recorridos.length > 1);
const elegido = ref(null);
const abierto = computed(() => {
    if (!varios.value) return 0;
    if (elegido.value !== null) return elegido.value;

    return props.linea.recorridos.findIndex((r) => !r.hora);
});

const conHorario = computed(() => props.linea.recorridos.filter((r) => r.hora).length);

function etiquetaDe(recorrido) {
    return recorrido.horarios.find((h) => h.inicio === recorrido.hora)?.etiqueta || recorrido.hora;
}

function alternar(indice) {
    elegido.value = abierto.value === indice ? -1 : indice;
}

function elegirHorario(indice, inicio) {
    carrito.elegirHorario(props.linea.uid, indice, inicio);
    // Se cierra y pasa al siguiente que falte.
    elegido.value = null;
}

/**
 * Ocupación de un horario: los que ya van y si con este grupo se pasa del cupo.
 * En mostrador no bloquea; solo se avisa para que el cajero lo decida.
 */
function cupoDe(h) {
    if (!h.capacidad) return null;

    const total = (h.ocupados || 0) + props.linea.cantidad;

    return {
        texto: `${h.ocupados || 0}/${h.capacidad}`,
        sePasa: total > h.capacidad,
        ayuda: total > h.capacidad
            ? `Ya van ${h.ocupados || 0}; con este grupo serían ${total} de ${h.capacidad}`
            : `Ya van ${h.ocupados || 0} de ${h.capacidad}`,
    };
}

/** Nombre del recorrido con el que choca esa hora, o null si no choca. */
function chocaCon(indice, inicio) {
    if (!chocaConOtro(props.linea, indice, inicio)) return null;

    const propio = props.linea.recorridos[indice];
    return props.linea.recorridos.find((otro, i) => i !== indice && otro.hora && otro.fecha === propio.fecha)?.nombre;
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
                    <template v-if="varios"> · {{ conHorario }} de {{ linea.recorridos.length }} horarios</template>
                    <template v-else-if="linea.recorridos[0]?.hora"> · {{ etiquetaDe(linea.recorridos[0]) }}</template>
                    <template v-else-if="linea.recorridos.length"> · sin horario</template>
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
                        :disabled="linea.cantidad >= 99"
                        aria-label="Agregar una persona"
                        @click="carrito.cambiarCantidad(linea.uid, linea.cantidad + 1)"
                    >
                        <Icono nombre="mas" clase="h-4 w-4" />
                    </button>
                    <span v-if="capacidad" class="text-xs" :class="linea.cantidad > capacidad ? 'text-amber-400' : 'text-slate-500'">
                        {{ linea.cantidad > capacidad ? `se pasa del cupo de ${capacidad}` : `cupo de ${capacidad}` }}
                    </span>
                </div>
            </div>

            <template v-if="linea.recorridos.length">
                <!-- Un solo nombre, aunque el paquete traiga varios recorridos -->
                <div>
                    <label class="etiqueta text-xs">Nombre del cliente</label>
                    <!-- Sin v-model: `linea` es una copia calculada y el nombre se perdería -->
                    <input
                        :value="linea.cliente" type="text" class="campo py-2 text-sm" placeholder="Opcional"
                        @input="carrito.cambiarCliente(linea.uid, $event.target.value)"
                    >
                </div>

                <div
                    v-for="(recorrido, i) in linea.recorridos" :key="i"
                    :class="varios ? 'rounded-lg border border-noche-700' : 'space-y-3'"
                >
                    <!-- Encabezado de cada recorrido del paquete; se toca para abrirlo -->
                    <button
                        v-if="varios" type="button"
                        class="flex w-full items-center gap-2 px-2.5 py-2 text-left"
                        @click="alternar(i)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] uppercase tracking-wide text-slate-500">Recorrido {{ i + 1 }}</span>
                            <span class="block truncate text-sm font-semibold text-slate-100">{{ recorrido.nombre }}</span>
                        </span>
                        <span v-if="recorrido.hora" class="tabular shrink-0 text-xs font-semibold text-acento-400">
                            {{ fechaCorta(recorrido.fecha) }} · {{ etiquetaDe(recorrido) }}
                        </span>
                        <span v-else class="chip shrink-0 bg-amber-500/15 text-amber-400">sin horario</span>
                        <Icono :nombre="abierto === i ? 'menos' : 'mas'" clase="h-3.5 w-3.5 shrink-0 text-slate-500" />
                    </button>

                    <div v-if="abierto === i" class="space-y-3" :class="{ 'border-t border-noche-700 p-2.5': varios }">
                        <div>
                            <label class="etiqueta text-xs">Fecha</label>
                            <SelectorFecha
                                :model-value="recorrido.fecha"
                                :producto-id="recorrido.producto_id"
                                @update:model-value="carrito.cambiarFecha(linea.uid, i, $event)"
                            />
                        </div>

                        <div>
                            <label class="etiqueta text-xs">Horario</label>

                            <p v-if="recorrido.cargandoHorarios" class="text-xs text-slate-500">Buscando horarios…</p>
                            <p v-else-if="recorrido.avisoHorarios" class="text-xs text-amber-400">{{ recorrido.avisoHorarios }}</p>

                            <div v-else class="grid grid-cols-3 gap-1.5">
                                <button
                                    v-for="h in recorrido.horarios" :key="h.inicio" type="button"
                                    class="flex flex-col items-center rounded border px-2 py-1.5 text-xs font-semibold transition"
                                    :class="recorrido.hora === h.inicio
                                        ? 'border-acento-500 bg-acento-500 text-white'
                                        : !h.libre
                                            ? 'cursor-not-allowed border-noche-700 text-slate-600 line-through'
                                            : chocaCon(i, h.inicio)
                                                ? 'cursor-not-allowed border-dashed border-noche-600 text-slate-600'
                                                : 'border-noche-600 text-slate-200 hover:border-acento-500'"
                                    :disabled="!h.libre || (!!chocaCon(i, h.inicio) && recorrido.hora !== h.inicio)"
                                    :title="!h.libre ? 'Esa salida ya pasó o no termina antes del cierre' : chocaCon(i, h.inicio) ? `A esa hora el grupo está en ${chocaCon(i, h.inicio)}` : cupoDe(h)?.ayuda"
                                    @click="elegirHorario(i, h.inicio)"
                                >
                                    <span>{{ h.etiqueta }}</span>
                                    <span
                                        v-if="h.libre && cupoDe(h) && h.ocupados"
                                        class="tabular text-[10px] font-normal"
                                        :class="recorrido.hora === h.inicio ? 'text-white/80' : cupoDe(h).sePasa ? 'text-amber-400' : 'text-slate-500'"
                                    >{{ cupoDe(h).texto }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="varios && conHorario < linea.recorridos.length && conHorario > 0" class="text-xs text-slate-500">
                    Al elegir una hora se sugiere la siguiente salida para el recorrido que sigue; puedes cambiarla.
                </p>
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
