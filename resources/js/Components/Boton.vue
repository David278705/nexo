<script setup>
defineProps({
    tipo: { type: String, default: "submit" },
    // El botón se deshabilita mientras envía, para evitar el doble registro
    // (documento 3, sección 6.2).
    enviando: { type: Boolean, default: false },
    ancho: { type: Boolean, default: false },
});
</script>

<template>
    <button
        :type="tipo"
        :disabled="enviando"
        class="inline-flex items-center justify-center rounded-[var(--radius-caja)] bg-accion px-4 py-2 font-medium text-white transition-colors hover:bg-accion-oscuro focus:outline-none focus:ring-2 focus:ring-accion focus:ring-offset-2 disabled:opacity-60"
        :class="ancho ? 'w-full' : ''"
    >
        <slot />
    </button>
</template>
