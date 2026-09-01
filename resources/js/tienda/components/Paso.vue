<script setup>
defineProps({
    numero: { type: Number, required: true },
    titulo: { type: String, required: true },
    activo: { type: Boolean, default: true },
});
</script>

<template>
    <section class="panel p-5 sm:p-6" :class="activo ? '' : 'opacity-50'">
        <h2 class="mb-5 flex items-center gap-3 text-lg font-semibold">
            <span
                class="flex h-8 w-8 shrink-0 items-center justify-center text-sm font-bold"
                :class="activo ? 'bg-terror-rojo text-white' : 'border border-terror-borde text-terror-texto'"
            >{{ numero }}</span>
            {{ titulo }}
        </h2>
        <slot />
    </section>
</template>
