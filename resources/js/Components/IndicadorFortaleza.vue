<script setup>
import { computed } from "vue";
import { fortalezaDeContrasena } from "@/Utilidades/fortalezaDeContrasena";

const props = defineProps({
    contrasena: {
        type: String,
        default: "",
    },
});

const fortaleza = computed(() => fortalezaDeContrasena(props.contrasena));

// El color nunca es el único portador de la información: al lado de la barra
// siempre va la palabra. Es la regla de accesibilidad del documento 3, 3.5.
const color = computed(
    () =>
        ({
            1: "bg-error",
            2: "bg-advertencia",
            3: "bg-accion",
            4: "bg-exito",
        })[fortaleza.value.nivel] ?? "bg-borde",
);
</script>

<template>
    <div v-if="fortaleza.nivel > 0" class="mt-2">
        <div class="h-1 w-full overflow-hidden rounded-full bg-borde">
            <div
                class="h-full transition-all"
                :class="color"
                :style="{ width: `${fortaleza.porcentaje}%` }"
            />
        </div>
        <p class="mt-1 text-xs text-texto-secundario">
            Fortaleza: {{ fortaleza.etiqueta }}
        </p>
    </div>
</template>
