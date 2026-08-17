<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSesion } from '../stores/sesion';

const sesion = useSesion();
const router = useRouter();

const cuenta = ref('');
const contrasena = ref('');
const error = ref('');
const enviando = ref(false);

async function entrar() {
    error.value = '';
    enviando.value = true;

    try {
        await sesion.iniciarSesion(cuenta.value.trim(), contrasena.value);
        router.push({ name: sesion.cajaAbierta ? 'venta' : 'apertura' });
    } catch (e) {
        error.value = e.message || 'No se pudo iniciar sesión.';
        contrasena.value = '';
    } finally {
        enviando.value = false;
    }
}
</script>

<template>
    <div class="flex h-full items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <p class="text-4xl font-extrabold tracking-tight text-sangre-500">ST</p>
                <h1 class="mt-3 text-2xl font-bold text-slate-100">Punto de Venta</h1>
                <p class="mt-1 text-sm text-slate-500">Ingresa con tu cuenta de operación</p>
            </div>

            <form class="tarjeta space-y-4 p-6" novalidate @submit.prevent="entrar">
                <div>
                    <label for="cuenta" class="etiqueta">Usuario o correo</label>
                    <input
                        id="cuenta"
                        v-model="cuenta"
                        type="text"
                        class="campo"
                        autocomplete="username"
                        autocapitalize="none"
                        autofocus
                        required
                    >
                </div>

                <div>
                    <label for="contrasena" class="etiqueta">Contraseña</label>
                    <input
                        id="contrasena"
                        v-model="contrasena"
                        type="password"
                        class="campo"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <p v-if="error" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">
                    {{ error }}
                </p>

                <button
                    type="submit"
                    class="btn-primario w-full"
                    :disabled="enviando || !cuenta || !contrasena"
                >
                    {{ enviando ? 'Entrando…' : 'Entrar' }}
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-600">
                ¿Buscas el panel de administración?
                <a href="/admin" class="text-slate-400 underline hover:text-slate-200">Ir al panel</a>
            </p>
        </div>
    </div>
</template>
