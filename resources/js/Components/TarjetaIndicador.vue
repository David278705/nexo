<script setup>
import { computed } from "vue";

/*
 * Tarjeta de indicador (documento 3, sección 3.4): cifra grande, etiqueta
 * debajo y variación frente al periodo anterior cuando aplica.
 *
 * La variación lleva la flecha y el signo además del color, porque el color no
 * puede ser el único portador de la información (3.5).
 */
const props = defineProps({
    valor: { type: [String, Number], required: true },
    etiqueta: { type: String, required: true },
    variacion: { type: String, default: "" },
    tendencia: {
        type: String,
        default: "",
        validator: (valor) => ["", "sube", "baja"].includes(valor),
    },
    /*
     * Una línea de contexto bajo la etiqueta, para las tarjetas que no comparan
     * contra un periodo anterior sino que explican la cifra: «Quedan 3 días»
     * bajo el límite de preaviso, «Tope legal: 12/09/2029» bajo la duración.
     */
    nota: { type: String, default: "" },
    notaTono: {
        type: String,
        default: "",
        validator: (valor) =>
            ["", "advertencia", "error"].includes(valor),
    },
});

const estiloDeLaTendencia = computed(
    () =>
        ({
            sube: "text-exito",
            baja: "text-error",
        })[props.tendencia] ?? "text-texto-secundario",
);

const flecha = computed(
    () => ({ sube: "▲", baja: "▼" })[props.tendencia] ?? "",
);

const estiloDeLaNota = computed(
    () =>
        ({
            advertencia: "text-advertencia-texto",
            error: "text-error-texto",
        })[props.notaTono] ?? "text-texto-secundario",
);
</script>

<template>
    <div
        class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-4"
    >
        <p class="text-cifra font-semibold tabular-nums text-texto">
            {{ valor }}
        </p>
        <p class="mt-1 text-apoyo text-texto-secundario">{{ etiqueta }}</p>
        <p v-if="variacion" class="mt-2 text-apoyo" :class="estiloDeLaTendencia">
            {{ flecha }} {{ variacion }}
        </p>

        <p v-if="nota" class="mt-2 text-apoyo" :class="estiloDeLaNota">
            {{ nota }}
        </p>
    </div>
</template>
