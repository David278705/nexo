<script setup>
/*
 * Las pestañas del sistema (documento 3, sección 5.3, y la barra `.tabs` del
 * prototipo). Las usa la ficha del empleado y las usará la pantalla de
 * parámetros de la empresa, que en el prototipo tiene cuatro.
 *
 * El contenido de todas las pestañas ya viene en las props de la pantalla, así
 * que cambiar de pestaña no pide nada al servidor: no hay espera y no se pierde
 * lo que se estuviera leyendo.
 *
 * Es una lista de botones con `role="tab"`, no una de enlaces: no llevan a otra
 * dirección. El recorrido con teclado es el estándar del patrón —flechas para
 * moverse, y el foco visible lo pone el `:focus-visible` global—.
 */
const props = defineProps({
    // Cada pestaña es `{ clave, titulo }`.
    pestanas: { type: Array, required: true },
    modelValue: { type: String, required: true },
});

const emit = defineEmits(["update:modelValue"]);

function mover(paso) {
    const actual = props.pestanas.findIndex(
        (pestana) => pestana.clave === props.modelValue,
    );
    const siguiente =
        (actual + paso + props.pestanas.length) % props.pestanas.length;

    emit("update:modelValue", props.pestanas[siguiente].clave);
}
</script>

<template>
    <div role="tablist" class="flex gap-1 border-b border-borde">
        <button
            v-for="pestana in pestanas"
            :id="`pestana-${pestana.clave}`"
            :key="pestana.clave"
            type="button"
            role="tab"
            :aria-selected="pestana.clave === modelValue"
            :aria-controls="`panel-${pestana.clave}`"
            :tabindex="pestana.clave === modelValue ? 0 : -1"
            class="border-b-2 px-4 py-2 text-tabla font-medium"
            :class="
                pestana.clave === modelValue
                    ? 'border-accion text-accion'
                    : 'border-transparent text-texto-secundario hover:text-texto'
            "
            @click="emit('update:modelValue', pestana.clave)"
            @keydown.arrow-right.prevent="mover(1)"
            @keydown.arrow-left.prevent="mover(-1)"
        >
            {{ pestana.titulo }}
        </button>
    </div>
</template>
