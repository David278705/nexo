<script setup>
import { computed } from "vue";

/*
 * Etiqueta de estado (documento 3, sección 3.4): texto corto sobre fondo claro
 * del color correspondiente. El texto siempre está, porque el color no puede
 * ser el único portador de la información (3.5).
 */
const props = defineProps({
    tono: {
        type: String,
        default: "neutro",
        validator: (valor) =>
            ["exito", "neutro", "advertencia", "error"].includes(valor),
    },
});

const estilos = computed(
    () =>
        ({
            exito: "bg-exito-fondo text-exito-texto",
            neutro: "bg-neutro-fondo text-texto-secundario",
            advertencia: "bg-advertencia-fondo text-advertencia-texto",
            error: "bg-error-fondo text-error-texto",
        })[props.tono],
);
</script>

<template>
    <span
        class="inline-block rounded-full px-2 py-0.5 text-apoyo font-semibold"
        :class="estilos"
    >
        <slot />
    </span>
</template>
