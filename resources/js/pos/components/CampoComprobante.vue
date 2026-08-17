<script setup>
import { ref, watch } from 'vue';
import Icono from './Icono.vue';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    requerido: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const entrada = ref(null);
const vistaPrevia = ref(null);

function seleccionar(evento) {
    const archivo = evento.target.files?.[0] || null;
    emit('update:modelValue', archivo);
}

// La vista previa se libera al cambiar de archivo: cada createObjectURL reserva
// memoria hasta que se revoca, y el cajero puede capturar decenas por turno.
watch(() => props.modelValue, (archivo, anterior) => {
    if (vistaPrevia.value) {
        URL.revokeObjectURL(vistaPrevia.value);
        vistaPrevia.value = null;
    }

    if (archivo) {
        vistaPrevia.value = URL.createObjectURL(archivo);
    } else if (anterior && entrada.value) {
        entrada.value.value = '';
    }
});

function quitar() {
    emit('update:modelValue', null);
    if (entrada.value) entrada.value.value = '';
}
</script>

<template>
    <div>
        <label class="etiqueta">
            Comprobante
            <span v-if="requerido" class="text-sangre-400">*</span>
            <span v-else class="font-normal text-slate-600">(opcional)</span>
        </label>

        <!-- capture="environment" abre la cámara trasera en tablet y no estorba en escritorio. -->
        <input
            ref="entrada"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            @change="seleccionar"
        >

        <div v-if="vistaPrevia" class="flex items-center gap-3 rounded-lg border border-noche-600 p-2">
            <img :src="vistaPrevia" alt="Vista previa del comprobante" class="h-16 w-16 rounded object-cover">
            <span class="min-w-0 flex-1 truncate text-sm text-slate-300">{{ modelValue?.name }}</span>
            <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5 text-sm text-sangre-400" @click="quitar">
                Quitar
            </button>
        </div>

        <button
            v-else
            type="button"
            class="btn-neutro w-full border-dashed"
            @click="entrada?.click()"
        >
            <Icono nombre="documento" clase="h-5 w-5" />
            Tomar foto o elegir archivo
        </button>
    </div>
</template>
