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
    titulo: { type: String, default: "" },
});

const estilos = computed(
    () =>
        ({
            informacion:
                "bg-informacion-fondo border-informacion-borde text-informacion-texto",
            exito: "bg-exito-fondo border-exito-borde text-exito-texto",
            advertencia:
                "bg-advertencia-fondo border-advertencia-borde text-advertencia-texto",
            error: "bg-error-fondo border-error-borde text-error-texto",
        })[props.tono],
);

// El color nunca es el único portador de información (3.5): el tono va también
// en palabras. Sin título visible, la palabra viaja para el lector de pantalla.
const nombreDelTono = computed(
    () =>
        ({
            informacion: "Información",
            exito: "Listo",
            advertencia: "Atención",
            error: "Error",
        })[props.tono],
);
</script>

<template>
    <div
        class="rounded-[var(--radius-caja)] border px-4 py-3 text-apoyo"
        :class="estilos"
        :role="tono === 'error' ? 'alert' : 'status'"
    >
        <p v-if="titulo" class="font-semibold">{{ titulo }}</p>
        <span v-else class="sr-only">{{ nombreDelTono }}:</span>
        <div :class="titulo ? 'mt-1' : ''"><slot /></div>
    </div>
</template>
