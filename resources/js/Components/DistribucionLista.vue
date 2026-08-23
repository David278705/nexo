<script setup>
import { computed } from "vue";

/*
 * Una distribución de conteos por categoría, en barras horizontales
 * (documento 3, sección 3.4 y `prototipos/sistema.html`): «Término indefinido
 * — 21», «Término fijo — 10», cada una con una barra proporcional al mayor
 * valor de la lista.
 *
 * Es de solo lectura y no decide nada: la lista, ya ordenada y con sus
 * etiquetas, llega escrita del servidor.
 */
const props = defineProps({
    /** @type {{ etiqueta: string, cuantos: number }[]} */
    items: { type: Array, required: true },
    vacio: { type: String, default: "Sin datos en este periodo." },
});

const maximo = computed(() =>
    Math.max(1, ...props.items.map((item) => item.cuantos)),
);

function anchoDeLaBarra(cuantos) {
    return `${Math.round((cuantos / maximo.value) * 100)}%`;
}
</script>

<template>
    <p v-if="items.length === 0" class="text-apoyo text-texto-secundario">
        {{ vacio }}
    </p>

    <ul v-else class="space-y-2">
        <li v-for="item in items" :key="item.etiqueta">
            <div class="flex items-center justify-between text-apoyo text-texto">
                <span>{{ item.etiqueta }}</span>
                <span class="tabular-nums">{{ item.cuantos }}</span>
            </div>
            <div class="mt-1 h-1.5 rounded-full bg-borde">
                <div
                    class="h-1.5 rounded-full bg-marca"
                    :style="{ width: anchoDeLaBarra(item.cuantos) }"
                />
            </div>
        </li>
    </ul>
</template>
