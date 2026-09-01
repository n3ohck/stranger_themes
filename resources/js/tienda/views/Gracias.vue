<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, dinero, fechaLarga, SITIO } from '../api';

const props = defineProps({ referencia: { type: String, required: true } });
const route = useRoute();

const compra = ref(null);
const cargando = ref(true);
const error = ref('');

// Si el regreso desde Stripe no alcanzó a confirmar, el webhook lo hará en
// segundos. Se reintenta un rato antes de rendirse.
const procesando = computed(() => route.query.procesando === '1' || compra.value?.estado === 'apartada');

async function cargar(intento = 0) {
    try {
        compra.value = await api.comprobante(props.referencia);

        if (compra.value.estado === 'apartada' && intento < 5) {
            setTimeout(() => cargar(intento + 1), 2000);
            return;
        }
    } catch (e) {
        error.value = e.primero;
    } finally {
        cargando.value = false;
    }
}

onMounted(() => cargar());

function hora(iso) {
    const [, h] = String(iso).split(' ');
    const [hh, mm] = h.split(':').map(Number);
    const d = new Date(); d.setHours(hh, mm, 0, 0);
    return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
}
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 py-12">
        <p v-if="cargando" class="text-center">Confirmando tu compra…</p>
        <p v-else-if="error" class="panel p-6 text-center text-terror-rojoClaro">{{ error }}</p>

        <template v-else-if="compra">
            <div class="mb-8 text-center">
                <h1 class="font-titular text-5xl leading-tight text-terror-rojo">
                    {{ procesando ? 'Estamos confirmando tu pago' : '¡Listo, nos vemos pronto!' }}
                </h1>
                <p class="mt-3 text-sm">
                    <template v-if="procesando">
                        Tu pago se está procesando. En cuanto se confirme te llega el correo con los boletos.
                    </template>
                    <template v-else>
                        Enviamos tus boletos a <span class="text-white">{{ compra.email }}</span>.
                    </template>
                </p>
            </div>

            <div class="panel p-6">
                <div v-if="compra.folio" class="mb-5 border-b border-terror-borde pb-4 text-center">
                    <p class="text-xs uppercase tracking-[0.14em]">Folio</p>
                    <p class="font-titular text-4xl text-white">{{ compra.folio }}</p>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt>Recorrido</dt><dd class="text-right text-white">{{ compra.producto }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>A nombre de</dt><dd class="text-right text-white">{{ compra.nombre }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Participantes</dt><dd class="text-right text-white">{{ compra.personas }}</dd>
                    </div>
                    <div v-if="compra.descuento > 0" class="flex justify-between gap-3">
                        <dt>Descuento <span class="text-white">{{ compra.codigo_descuento }}</span></dt>
                        <dd class="tabular text-right text-terror-rojoClaro">−{{ dinero(compra.descuento) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Total pagado</dt><dd class="tabular text-right text-white">{{ dinero(compra.total) }}</dd>
                    </div>
                </dl>

                <div class="mt-6 border-t border-terror-borde pt-5">
                    <p class="etiqueta-terror">Tus horarios</p>
                    <ul class="space-y-2">
                        <li v-for="(r, i) in compra.reservaciones" :key="i" class="flex justify-between gap-3 text-sm">
                            <span class="text-white">{{ r.producto }}</span>
                            <span>{{ fechaLarga(r.inicio.split(' ')[0]) }} · {{ hora(r.inicio) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-6 border-t border-terror-borde pt-5 text-sm">
                    <p class="etiqueta-terror">Dónde</p>
                    <p class="text-white">{{ compra.sucursal.nombre }}</p>
                    <p class="mt-1">{{ compra.sucursal.direccion }}</p>
                    <a v-if="compra.sucursal.ubicacion" :href="compra.sucursal.ubicacion" target="_blank" rel="noopener"
                       class="mt-2 inline-block text-terror-rojoClaro underline">Ver cómo llegar</a>
                </div>

                <p class="mt-6 border-t border-terror-borde pt-5 text-xs">
                    Llega 15 minutos antes. Presenta este folio o el correo de confirmación.
                </p>
            </div>

            <div class="mt-6 text-center">
                <a :href="SITIO" class="btn-linea">Volver al sitio</a>
            </div>
        </template>
    </div>
</template>
