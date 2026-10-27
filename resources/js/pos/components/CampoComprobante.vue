<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import Icono from './Icono.vue';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    requerido: { type: Boolean, default: false },
    etiqueta: { type: String, default: 'Comprobante' },
});

const emit = defineEmits(['update:modelValue']);

const entrada = ref(null);
const vistaPrevia = ref(null);

// Cámara
const video = ref(null);
const camaraAbierta = ref(false);
const abriendoCamara = ref(false);
const errorCamara = ref('');
let flujo = null;

/**
 * En escritorio el atributo capture no hace nada: solo abre el explorador de
 * archivos. Para tomar la foto desde la webcam hay que pedir la cámara y
 * capturar un cuadro a mano, que es lo que hace esto.
 */
async function abrirCamara() {
    errorCamara.value = '';
    abriendoCamara.value = true;

    try {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Este navegador no permite usar la cámara.');
        }

        flujo = await navigator.mediaDevices.getUserMedia({
            // facingMode se ignora en laptop y elige la trasera en tablet.
            video: { facingMode: 'environment', width: { ideal: 1280 } },
            audio: false,
        });

        camaraAbierta.value = true;
        // El <video> no existe hasta que el v-if lo monta.
        await new Promise((r) => setTimeout(r, 0));

        if (video.value) {
            video.value.srcObject = flujo;
            await video.value.play();
        }
    } catch (e) {
        cerrarCamara();
        errorCamara.value = mensajeDe(e);
    } finally {
        abriendoCamara.value = false;
    }
}

/** El navegador devuelve nombres técnicos; aquí se traducen a algo accionable. */
function mensajeDe(e) {
    if (e.name === 'NotAllowedError') {
        return 'No diste permiso para usar la cámara. Permítelo en el candado de la barra de direcciones.';
    }

    if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
        return 'No se encontró ninguna cámara conectada.';
    }

    if (e.name === 'NotReadableError') {
        return 'La cámara está siendo usada por otro programa.';
    }

    // getUserMedia solo funciona en https o en localhost.
    if (!window.isSecureContext) {
        return 'La cámara solo funciona en conexiones seguras (https).';
    }

    return e.message || 'No se pudo abrir la cámara.';
}

function capturar() {
    if (!video.value) return;

    const lienzo = document.createElement('canvas');
    lienzo.width = video.value.videoWidth;
    lienzo.height = video.value.videoHeight;
    lienzo.getContext('2d').drawImage(video.value, 0, 0);

    lienzo.toBlob(
        (blob) => {
            if (!blob) return;

            emit('update:modelValue', new File([blob], `comprobante-${Date.now()}.jpg`, { type: 'image/jpeg' }));
            cerrarCamara();
        },
        'image/jpeg',
        0.85
    );
}

function cerrarCamara() {
    if (flujo) {
        flujo.getTracks().forEach((t) => t.stop());
        flujo = null;
    }

    camaraAbierta.value = false;
}

// Si se cambia de pestaña con la cámara abierta, el indicador del equipo se
// quedaría encendido; apagarla al desmontar lo evita.
onBeforeUnmount(cerrarCamara);

function seleccionarArchivo(evento) {
    emit('update:modelValue', evento.target.files?.[0] || null);
}

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
            {{ etiqueta }}
            <span v-if="requerido" class="text-sangre-400">*</span>
            <span v-else class="font-normal text-slate-600">(opcional)</span>
        </label>

        <input ref="entrada" type="file" accept="image/*" class="hidden" @change="seleccionarArchivo">

        <!-- Foto ya tomada o elegida -->
        <div v-if="vistaPrevia" class="flex items-center gap-3 rounded-lg border border-noche-600 p-2">
            <img :src="vistaPrevia" alt="Vista previa del comprobante" class="h-16 w-16 rounded object-cover">
            <span class="min-w-0 flex-1 truncate text-sm text-slate-300">{{ modelValue?.name }}</span>
            <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5 text-sm text-sangre-400" @click="quitar">
                Quitar
            </button>
        </div>

        <!-- Cámara en vivo -->
        <div v-else-if="camaraAbierta" class="overflow-hidden rounded-lg border border-noche-600">
            <video ref="video" class="w-full bg-black" playsinline muted></video>
            <div class="flex gap-2 p-2">
                <button type="button" class="btn-neutro min-h-0 flex-1 px-3 py-2 text-sm" @click="cerrarCamara">
                    Cancelar
                </button>
                <button type="button" class="btn-primario min-h-0 flex-1 px-3 py-2 text-sm" @click="capturar">
                    Tomar foto
                </button>
            </div>
        </div>

        <!-- Sin foto todavía -->
        <div v-else class="flex gap-2">
            <button type="button" class="btn-neutro flex-1" :disabled="abriendoCamara" @click="abrirCamara">
                <Icono nombre="camara" clase="h-5 w-5" />
                {{ abriendoCamara ? 'Abriendo…' : 'Tomar foto' }}
            </button>
            <button type="button" class="btn-neutro flex-1" @click="entrada?.click()">
                <Icono nombre="documento" clase="h-5 w-5" />
                Elegir archivo
            </button>
        </div>

        <p v-if="errorCamara" class="mt-2 text-xs text-amber-400">{{ errorCamara }}</p>
    </div>
</template>
