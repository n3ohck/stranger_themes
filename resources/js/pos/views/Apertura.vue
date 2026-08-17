<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSesion } from '../stores/sesion';
import { dinero } from '../utils/formato';

const sesion = useSesion();
const router = useRouter();

const monto = ref('');
const error = ref('');
const enviando = ref(false);

// Denominaciones de billete comunes, para armar el fondo sin teclear.
const atajos = [200, 500, 1000, 1500, 2000];

const montoNumerico = computed(() => Number(monto.value) || 0);

function sumar(cantidad) {
    monto.value = String(montoNumerico.value + cantidad);
}

async function abrir() {
    error.value = '';
    enviando.value = true;

    try {
        await sesion.abrirCaja(montoNumerico.value);
        router.push({ name: 'venta' });
    } catch (e) {
        error.value = e.listaErrores?.[0] || e.message || 'No se pudo abrir la caja.';
    } finally {
        enviando.value = false;
    }
}

function salir() {
    sesion.cerrarSesion();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex h-full items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="tarjeta overflow-hidden">
                <div class="border-b border-noche-600 p-6">
                    <h1 class="text-2xl font-bold text-slate-100">Apertura de Caja</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Hola {{ sesion.usuario?.nombre }}. Ingresa el fondo con el que inicias el turno
                        en {{ sesion.sucursal?.nombre }}.
                    </p>
                </div>

                <div class="space-y-5 p-6">
                    <div>
                        <label for="monto" class="etiqueta">Fondo inicial</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-500">$</span>
                            <input
                                id="monto"
                                v-model="monto"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="campo tabular pl-9 text-2xl font-semibold"
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="atajo in atajos"
                            :key="atajo"
                            type="button"
                            class="btn-neutro min-h-[2.5rem] px-3 text-sm"
                            @click="sumar(atajo)"
                        >
                            +{{ dinero(atajo) }}
                        </button>
                        <button type="button" class="btn-fantasma min-h-[2.5rem] px-3 text-sm" @click="monto = ''">
                            Limpiar
                        </button>
                    </div>

                    <p v-if="error" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">
                        {{ error }}
                    </p>

                    <p class="text-xs text-slate-500">
                        Este monto se compara contra el efectivo contado al hacer el corte, así que
                        conviene contar el cajón antes de continuar.
                    </p>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-noche-600 bg-noche-700/40 px-6 py-4">
                    <button type="button" class="btn-fantasma" @click="salir">Cerrar sesión</button>
                    <button type="button" class="btn-primario flex-1 sm:flex-none" :disabled="enviando" @click="abrir">
                        {{ enviando ? 'Abriendo…' : 'Realizar apertura' }}
                    </button>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-slate-600">
                ¿Solo vienes a revisar el calendario?
                <RouterLink :to="{ name: 'reservas' }" class="text-slate-400 underline hover:text-slate-200">
                    Ver reservaciones
                </RouterLink>
            </p>
        </div>
    </div>
</template>
