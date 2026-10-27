<script setup>
import { computed, ref } from 'vue';
import { digitosTelefono, formatoTelefono, telefonoValido } from '../validacion';

/**
 * Teléfono a 10 dígitos con máscara '614 595 6833'. Acepta pegar con lada
 * (+52) o con guiones y lo normaliza. El cursor se conserva en su lugar aunque
 * la máscara meta espacios, para poder corregir un dígito en medio.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const tocado = ref(false);
const error = computed(() => tocado.value && !telefonoValido(props.modelValue));

function alEscribir(evento) {
    const campo = evento.target;
    const crudos = campo.value.replace(/\D/g, '');
    // Si trae lada (+52) la máscara la quita; el cursor se recorre lo mismo.
    const sinLada = crudos.length > 10 && crudos.startsWith('52') ? 2 : 0;
    const digitosAntes = Math.max(0, campo.value.slice(0, campo.selectionStart).replace(/\D/g, '').length - sinLada);
    const formateado = formatoTelefono(campo.value);

    campo.value = formateado;
    emit('update:modelValue', formateado);

    // Lleva el cursor justo después del mismo número de dígitos que tenía antes.
    let posicion = 0;
    let vistos = 0;
    while (posicion < formateado.length && vistos < digitosAntes) {
        if (/\d/.test(formateado[posicion])) vistos++;
        posicion++;
    }
    campo.setSelectionRange(posicion, posicion);
}
</script>

<template>
    <div>
        <label class="etiqueta-terror" for="telefono">Teléfono <span class="normal-case">(opcional)</span></label>
        <input
            id="telefono"
            :value="modelValue"
            type="tel"
            inputmode="numeric"
            autocomplete="tel-national"
            placeholder="614 123 4567"
            class="campo-terror tabular-nums tracking-wide"
            :class="{ '!border-terror-rojoClaro': error }"
            :aria-invalid="error"
            aria-describedby="telefono-ayuda"
            @input="alEscribir"
            @blur="tocado = true"
        >
        <p id="telefono-ayuda" class="mt-1.5 text-xs" :class="error ? 'text-terror-rojoClaro' : 'text-terror-texto/80'">
            {{ error ? 'Escribe los 10 dígitos de tu teléfono.' : 'A 10 dígitos, por si necesitamos avisarte de un cambio.' }}
        </p>
    </div>
</template>
