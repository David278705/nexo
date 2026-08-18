<script setup>
import EstadoVacio from "@/Components/EstadoVacio.vue";

/*
 * La tabla del sistema. Los siete listados usan esta, no una copia: encabezado
 * fijo al hacer scroll, filas de 44 px, cifras a la derecha y fila completa
 * clicable (documento 3, sección 3.4).
 *
 * Cada columna es `{ clave, titulo, numerica }`. El contenido de una celda se
 * puede reemplazar con el slot `celda-<clave>`, que recibe la fila entera.
 */
defineProps({
    columnas: { type: Array, required: true },
    filas: { type: Array, required: true },
    // Campo que identifica la fila. `id` en todos los módulos.
    clave: { type: String, default: "id" },
    tituloVacio: { type: String, default: "Aún no hay registros" },
    descripcionVacio: { type: String, default: "" },
});

const emit = defineEmits(["abrir"]);
</script>

<template>
    <div
        class="overflow-hidden rounded-[var(--radius-modal)] border border-borde bg-superficie"
    >
        <div v-if="filas.length === 0">
            <EstadoVacio :titulo="tituloVacio" :descripcion="descripcionVacio">
                <slot name="accion-vacio" />
            </EstadoVacio>
        </div>

        <div v-else class="max-h-[70vh] overflow-auto">
            <table class="w-full border-collapse text-tabla">
                <thead>
                    <tr>
                        <th
                            v-for="columna in columnas"
                            :key="columna.clave"
                            scope="col"
                            class="sticky top-0 z-10 bg-marca px-3 py-2 text-apoyo font-semibold text-texto-invertido"
                            :class="columna.numerica ? 'text-right' : 'text-left'"
                        >
                            {{ columna.titulo }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="fila in filas"
                        :key="fila[clave]"
                        tabindex="0"
                        class="h-fila cursor-pointer border-b border-borde hover:bg-superficie-resaltada"
                        @click="emit('abrir', fila)"
                        @keydown.enter="emit('abrir', fila)"
                        @keydown.space.prevent="emit('abrir', fila)"
                    >
                        <td
                            v-for="columna in columnas"
                            :key="columna.clave"
                            class="px-3 text-texto"
                            :class="
                                columna.numerica
                                    ? 'text-right tabular-nums'
                                    : 'text-left'
                            "
                        >
                            <slot
                                :name="`celda-${columna.clave}`"
                                :fila="fila"
                            >
                                {{ fila[columna.clave] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
