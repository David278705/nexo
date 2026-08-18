<script setup>
import { computed } from "vue";

const props = defineProps({
    // `error` solo cuando algo está mal de verdad: es la regla de color del
    // documento 3, sección 3.1.
    tono: {
        type: String,
        default: "informacion",
        validator: (valor) =>
            ["informacion", "exito", "advertencia", "error"].includes(valor),
    },
});

const estilos = computed(
    () =>
        ({
            informacion: "border-accion text-texto",
            exito: "border-exito text-texto",
            advertencia: "border-advertencia text-texto",
            error: "border-error text-error",
        })[props.tono],
);
</script>

<template>
    <div
        class="rounded-[var(--radius-caja)] border-l-4 bg-fondo px-4 py-3 text-sm"
        :class="estilos"
        role="status"
    >
        <slot />
    </div>
</template>
