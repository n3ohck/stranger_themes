<script setup>
import { SITIO } from './api';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-terror-borde">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                <a :href="SITIO" class="block shrink-0" aria-label="Stranger Themes">
                    <img src="/images/logo-stranger-themes.webp" alt="Stranger Themes"
                         class="h-12 w-auto sm:h-16" width="800" height="314">
                </a>
                <a :href="SITIO" class="text-xs font-semibold uppercase tracking-[0.14em] text-terror-texto hover:text-white">
                    Volver al sitio
                </a>
            </div>
        </header>

        <main class="flex-1">
            <RouterView />
        </main>

        <footer class="border-t border-terror-borde">
            <div class="mx-auto max-w-5xl px-4 py-6 text-center text-xs text-terror-texto">
                <p>Stranger Themes® {{ new Date().getFullYear() }}. Todos los derechos reservados.</p>
                <p class="mt-1">Pago seguro procesado por Stripe. No almacenamos datos de tu tarjeta.</p>
            </div>
        </footer>
    </div>
</template>
