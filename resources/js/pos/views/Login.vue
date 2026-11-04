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

/*
 * La caja la comparten varios cajeros, así que el navegador no debe guardar ni
 * autocompletar la cuenta de nadie. `autocomplete="off"` no basta: Chrome lo
 * ignora en formularios de acceso y ofrece guardar en cuanto ve un campo
 * type="password". Donde se puede, la contraseña va en un campo de texto que se
 * enmascara por CSS, y sin campo de contraseña el navegador no reconoce un login
 * que guardar. Donde no hay `-webkit-text-security` se queda como password con
 * "new-password", que al menos evita que rellene una cuenta guardada.
 */
const enmascararPorCss = typeof CSS !== 'undefined' && CSS.supports('-webkit-text-security', 'disc');

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

            <form class="tarjeta space-y-4 p-6" novalidate autocomplete="off" @submit.prevent="entrar">
                <div>
                    <label for="cuenta" class="etiqueta">Usuario o correo</label>
                    <input
                        id="cuenta"
                        v-model="cuenta"
                        type="text"
                        name="pos-cuenta"
                        class="campo"
                        autocomplete="off"
                        autocapitalize="none"
                        autocorrect="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore
                        autofocus
                        required
                    >
                </div>

                <div>
                    <label for="contrasena" class="etiqueta">Contraseña</label>
                    <input
                        id="contrasena"
                        v-model="contrasena"
                        :type="enmascararPorCss ? 'text' : 'password'"
                        name="pos-clave"
                        class="campo"
                        :class="{ 'campo-enmascarado': enmascararPorCss }"
                        :autocomplete="enmascararPorCss ? 'off' : 'new-password'"
                        autocapitalize="none"
                        autocorrect="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore
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

<style scoped>
.campo-enmascarado {
    -webkit-text-security: disc;
}
</style>
