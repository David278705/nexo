<script setup>
import CampoTexto from "@/Components/CampoTexto.vue";

/*
 * El selector de fecha es el nativo del navegador: trae calendario y además
 * deja escribir la fecha directamente, que es justo lo que pide el documento 3,
 * sección 3.4. Envuelve a `CampoTexto` para no repetir etiqueta, ayuda, error
 * ni las medidas del control.
 *
 * El valor viaja siempre como `AAAA-MM-DD`, que es lo que espera Laravel para
 * una columna `date`.
 */
defineProps({
    modelValue: { type: String, default: "" },
    etiqueta: { type: String, required: true },
    error: { type: String, default: "" },
    ayuda: { type: String, default: "" },
    opcional: { type: Boolean, default: false },
    soloLectura: { type: Boolean, default: false },
    // Límites del calendario nativo, en `AAAA-MM-DD`. La validación de verdad
    // sigue siendo la del servidor: esto es la cortesía de no dejar elegir en
    // el navegador una fecha que ya se sabe que se va a rechazar.
    minimo: { type: String, default: "" },
    maximo: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "salir"]);
</script>

<template>
    <CampoTexto
        tipo="date"
        :model-value="modelValue"
        :etiqueta="etiqueta"
        :error="error"
        :ayuda="ayuda"
        :opcional="opcional"
        :solo-lectura="soloLectura"
        :minimo="minimo"
        :maximo="maximo"
        @update:model-value="emit('update:modelValue', $event)"
        @salir="emit('salir', $event)"
    />
</template>
