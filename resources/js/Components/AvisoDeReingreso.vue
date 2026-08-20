<script setup>
import { computed } from "vue";
import Aviso from "@/Components/Aviso.vue";

/*
 * La advertencia de reingreso del documento 1, sección 5.6.
 *
 * Los textos no se escriben aquí: llegan del servidor, donde son constantes de
 * `AdvertenciaDeReingreso` y donde una prueba comprueba que siguen siendo los
 * literales del documento. Este componente solo decide cómo se ven.
 *
 * Vive en `Components/` porque el asistente lo enseña dos veces —bajo el campo
 * del documento y otra vez en el resumen, cuando ya se sabe la fecha de
 * ingreso—, y en F2-09 lo volverá a necesitar quien registre una vinculación
 * desde otro sitio.
 *
 * El tono es advertencia y no error: un reingreso es legítimo. Lo que cambia
 * cuando ocurre dentro de los 30 días siguientes a la salida es el énfasis, y va
 * en palabras además de en negrilla, porque el color nunca es el único portador
 * de información.
 */
const props = defineProps({
    reingreso: { type: Object, default: null },
});

const titulo = computed(() =>
    props.reingreso?.reciente
        ? "Reingreso dentro de los 30 días siguientes a la salida"
        : "Esta persona ya estuvo vinculada",
);
</script>

<template>
    <Aviso v-if="reingreso" tono="advertencia" :titulo="titulo">
        <p>{{ reingreso.texto }}</p>

        <p
            v-if="reingreso.detalle"
            class="mt-2"
            :class="reingreso.reciente ? 'font-semibold' : ''"
        >
            {{ reingreso.detalle }}
        </p>

        <p class="mt-2">{{ reingreso.nota }}</p>
    </Aviso>
</template>
