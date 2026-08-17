<script setup>
import { computed } from 'vue';
import { dinero } from '../utils/formato';
import Icono from './Icono.vue';

const props = defineProps({
    producto: { type: Object, required: true },
});

defineEmits(['agregar']);

const etiquetaTipo = {
    tour: 'Tour',
    tour_paquete: 'Paquete',
    articulo: 'Producto',
    diferencias: 'Diferencia',
};

const colorTipo = {
    tour: 'bg-sangre-600/20 text-sangre-400',
    tour_paquete: 'bg-acento-600/25 text-acento-400',
    articulo: 'bg-emerald-600/20 text-emerald-400',
    diferencias: 'bg-amber-600/20 text-amber-400',
};

const agotado = computed(
    () => props.producto.controla_existencia && props.producto.existencia <= 0
);

const detalleTours = computed(() => {
    if (!props.producto.tours.length) return null;

    return `${props.producto.tours.length} tours · ${props.producto.tours.map((t) => t.nombre).join(', ')}`;
});
</script>

<template>
    <div
        class="tarjeta flex flex-col justify-between p-4 transition"
        :class="agotado ? 'opacity-45' : 'hover:border-acento-500/60'"
    >
        <div class="min-w-0">
            <div class="mb-2 flex items-start justify-between gap-2">
                <span class="chip shrink-0" :class="colorTipo[producto.tipo]">
                    {{ etiquetaTipo[producto.tipo] || producto.tipo }}
                </span>
                <span class="tabular shrink-0 text-lg font-bold text-slate-100">
                    {{ dinero(producto.precio) }}
                </span>
            </div>

            <h3 class="text-base font-semibold leading-snug text-slate-100">
                {{ producto.nombre }}
            </h3>

            <p v-if="detalleTours" class="mt-1 line-clamp-2 text-xs text-slate-500">
                {{ detalleTours }}
            </p>

            <p v-if="producto.controla_existencia" class="mt-1 text-xs" :class="agotado ? 'text-sangre-400' : 'text-slate-500'">
                {{ agotado ? 'Sin existencia' : `${producto.existencia} en existencia` }}
            </p>
        </div>

        <button
            type="button"
            class="btn-neutro mt-4 w-full"
            :disabled="agotado"
            @click="$emit('agregar', producto)"
        >
            <Icono nombre="mas" clase="h-4 w-4" />
            Añadir
        </button>
    </div>
</template>
