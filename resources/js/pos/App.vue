<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSesion } from './stores/sesion';
import { dinero, horaCorta } from './utils/formato';
import Icono from './components/Icono.vue';

const sesion = useSesion();
const route = useRoute();
const router = useRouter();

const conNavegacion = computed(() => sesion.autenticado && route.name !== 'login' && route.name !== 'apertura');

const enlaces = [
    { nombre: 'venta', etiqueta: 'Venta', icono: 'carrito' },
    { nombre: 'historial', etiqueta: 'Historial', icono: 'documento' },
    { nombre: 'reservas', etiqueta: 'Reservas', icono: 'calendario' },
    { nombre: 'movimientos', etiqueta: 'Movimientos', icono: 'salida' },
    { nombre: 'corte', etiqueta: 'Corte', icono: 'caja' },
];

function salir() {
    sesion.cerrarSesion();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex h-full flex-col bg-noche-900">
        <header v-if="conNavegacion" class="no-imprimir shrink-0 border-b border-noche-700 bg-noche-800">
            <div class="flex items-center gap-2 px-3 py-2 sm:px-4">
                <div class="flex shrink-0 items-baseline gap-2 pr-2">
                    <span class="font-extrabold tracking-tight text-sangre-500">ST</span>
                    <span class="hidden text-xs text-slate-500 sm:inline">{{ sesion.sucursal?.nombre }}</span>
                </div>

                <nav class="flex flex-1 gap-1 overflow-x-auto">
                    <RouterLink
                        v-for="enlace in enlaces"
                        :key="enlace.nombre"
                        :to="{ name: enlace.nombre }"
                        class="flex min-h-[2.75rem] items-center gap-2 rounded-lg px-3 text-sm font-semibold whitespace-nowrap transition"
                        :class="route.name === enlace.nombre
                            ? 'bg-acento-500 text-white'
                            : 'text-slate-400 hover:bg-noche-700 hover:text-slate-100'"
                    >
                        <Icono :nombre="enlace.icono" clase="h-5 w-5" />
                        <span>{{ enlace.etiqueta }}</span>
                    </RouterLink>
                </nav>

                <div class="flex shrink-0 items-center gap-3">
                    <div v-if="sesion.apertura" class="hidden text-right leading-tight md:block">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Caja desde</p>
                        <p class="text-xs font-semibold text-emerald-400">
                            {{ horaCorta(sesion.apertura.abierta_en) }} · fondo {{ dinero(sesion.apertura.monto_apertura) }}
                        </p>
                    </div>

                    <button type="button" class="btn-fantasma px-3" :title="`Salir (${sesion.usuario?.nombre})`" @click="salir">
                        <Icono nombre="salir" />
                        <span class="hidden lg:inline">Salir</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="min-h-0 flex-1">
            <!--
                Se espera solo al arranque. Si se condicionara a `cargando`, cada
                refresco de sesión desmontaría la vista activa y el cajero
                perdería lo que tuviera en pantalla (por ejemplo el ticket de
                corte, que se muestra justo después de refrescar).
            -->
            <div v-if="!sesion.inicializado" class="flex h-full items-center justify-center text-slate-500">
                Cargando punto de venta…
            </div>
            <RouterView v-else />
        </main>
    </div>
</template>
