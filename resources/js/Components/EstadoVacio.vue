<script setup>
/*
 * Una pantalla sin datos nunca dice «No hay datos» (documento 3, sección 2.5):
 * explica qué es esta sección y ofrece la primera acción. El botón va en el
 * slot, porque cada listado tiene el suyo.
 */
defineProps({
    titulo: { type: String, required: true },
    descripcion: { type: String, default: "" },
});
</script>

<template>
    <div class="px-6 py-14 text-center">
        <p class="text-seccion font-semibold text-texto">{{ titulo }}</p>
        <p
            v-if="descripcion"
            class="mx-auto mt-2 max-w-prose text-cuerpo text-texto-secundario"
        >
            {{ descripcion }}
        </p>
        <div class="mt-4 flex justify-center">
            <slot />
        </div>
    </div>
</template>
