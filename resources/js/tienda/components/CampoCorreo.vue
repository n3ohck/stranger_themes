<script setup>
import { computed, ref } from 'vue';
import { correoValido, dominioCorregido, DOMINIOS_COMUNES } from '../validacion';

/**
 * Correo con sugerencias de dominio. Al escribir la arroba aparece la lista de
 * dominios comunes filtrada por lo que se lleve escrito; se acepta con Tab, Enter
 * o un clic, y si el dominio es otro se sigue escribiendo como siempre. Es un
 * combobox de ARIA: las flechas mueven la opción y Escape cierra la lista.
 *
 * Si el dominio parece un error de dedo ('gmial.com') se ofrece la corrección.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const enfocado = ref(false);
const tocado = ref(false);
const cerrada = ref(false);
const activa = ref(0);

const partes = computed(() => {
    const valor = props.modelValue;
    const arroba = valor.lastIndexOf('@');

    return arroba === -1
        ? null
        : { usuario: valor.slice(0, arroba), dominio: valor.slice(arroba + 1).toLowerCase() };
});

const sugerencias = computed(() => {
    const p = partes.value;
    if (!p || !p.usuario || p.usuario.includes(' ')) return [];

    return DOMINIOS_COMUNES.filter((d) => d.startsWith(p.dominio) && d !== p.dominio);
});

const abierta = computed(() => enfocado.value && !cerrada.value && sugerencias.value.length > 0);

const correccion = computed(() => {
    const p = partes.value;
    if (!p || enfocado.value) return null;

    const dominio = dominioCorregido(p.dominio);
    return dominio ? `${p.usuario}@${dominio}` : null;
});

const error = computed(() =>
    tocado.value && !enfocado.value && props.modelValue.trim() !== '' && !correoValido(props.modelValue)
);

function alEscribir(evento) {
    // Sin espacios: en un correo nunca van y el teclado del celular los mete.
    emit('update:modelValue', evento.target.value.replace(/\s/g, ''));
    cerrada.value = false;
    activa.value = 0;
}

function elegir(dominio) {
    emit('update:modelValue', `${partes.value.usuario}@${dominio}`);
    cerrada.value = true;
}

function alTeclear(evento) {
    if (!abierta.value) return;

    if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        activa.value = (activa.value + 1) % sugerencias.value.length;
    } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        activa.value = (activa.value - 1 + sugerencias.value.length) % sugerencias.value.length;
    } else if (evento.key === 'Enter' || evento.key === 'Tab') {
        // Tab acepta la sugerencia y se queda en el campo; el siguiente Tab avanza.
        evento.preventDefault();
        elegir(sugerencias.value[activa.value]);
    } else if (evento.key === 'Escape') {
        cerrada.value = true;
    }
}

function alSalir() {
    enfocado.value = false;
    tocado.value = true;
}
</script>

<template>
    <div class="relative">
        <label class="etiqueta-terror" for="correo">Correo</label>
        <input
            id="correo"
            :value="modelValue"
            type="email"
            inputmode="email"
            autocomplete="email"
            autocapitalize="off"
            spellcheck="false"
            placeholder="Ahí llegan tus boletos"
            class="campo-terror"
            :class="{ '!border-terror-rojoClaro': error }"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="abierta"
            aria-controls="correo-dominios"
            :aria-activedescendant="abierta ? `correo-dominio-${activa}` : undefined"
            :aria-invalid="error"
            @input="alEscribir"
            @keydown="alTeclear"
            @focus="enfocado = true"
            @blur="alSalir"
        >

        <ul
            v-show="abierta"
            id="correo-dominios"
            role="listbox"
            class="absolute left-0 right-0 z-20 mt-1 max-h-64 overflow-auto border border-terror-borde bg-terror-panel py-1 shadow-xl shadow-black/50"
        >
            <li
                v-for="(dominio, i) in sugerencias"
                :id="`correo-dominio-${i}`"
                :key="dominio"
                role="option"
                :aria-selected="i === activa"
                class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm transition"
                :class="i === activa ? 'bg-terror-rojo/20 text-white' : 'text-terror-texto hover:bg-white/5 hover:text-white'"
                @mousedown.prevent="elegir(dominio)"
                @mouseenter="activa = i"
            >
                <span class="truncate">
                    <span class="opacity-70">{{ partes.usuario }}@</span><span class="font-semibold text-white">{{ dominio }}</span>
                </span>
                <span v-if="i === activa" class="shrink-0 border border-terror-borde px-1.5 py-0.5 text-[10px] uppercase tracking-wider">Tab</span>
            </li>
        </ul>

        <p v-if="error" class="mt-1.5 text-xs text-terror-rojoClaro">
            Revisa tu correo: debe verse como nombre@dominio.com
        </p>
        <p v-else-if="correccion" class="mt-1.5 text-xs text-terror-texto">
            ¿Quisiste decir
            <button type="button" class="font-semibold text-white underline decoration-terror-rojo underline-offset-2"
                    @click="emit('update:modelValue', correccion)">{{ correccion }}</button>?
        </p>
    </div>
</template>
