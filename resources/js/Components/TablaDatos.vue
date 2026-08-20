<script setup>
import EstadoVacio from "@/Components/EstadoVacio.vue";

/*
 * La tabla del sistema. Los siete listados usan esta, no una copia: encabezado
 * fijo al hacer scroll, filas de 44 px, cifras a la derecha y fila completa
 * clicable (documento 3, sección 3.4).
 *
 * Cada columna es `{ clave, titulo, numerica, ordenable }`. El contenido de una
 * celda se puede reemplazar con el slot `celda-<clave>`, que recibe la fila
 * entera.
 *
 * El ordenamiento no se hace aquí: la columna pulsada se emite y el listado se
 * la pide al servidor, porque la tabla solo tiene delante una página de 25 y
 * ordenar esas 25 daría un orden falso.
 */
defineProps({
    columnas: { type: Array, required: true },
    filas: { type: Array, required: true },
    // Campo que identifica la fila. `id` en todos los módulos.
    clave: { type: String, default: "id" },
    /*
     * En los listados la fila completa lleva a la ficha, pero hay tablas que
     * solo informan —el historial de periodos del contrato, por ejemplo—. Ahí
     * la fila no se pinta como clicable ni se recorre con el tabulador, porque
     * ofrecer un clic que no hace nada es peor que no ofrecerlo.
     */
    clicable: { type: Boolean, default: true },
    tituloVacio: { type: String, default: "Aún no hay registros" },
    descripcionVacio: { type: String, default: "" },
    // La columna por la que ya viene ordenada la página y en qué sentido.
    ordenPor: { type: String, default: "" },
    ordenDireccion: { type: String, default: "asc" },
});

const emit = defineEmits(["abrir", "ordenar"]);

// El lector de pantalla necesita saber por dónde está ordenada la tabla; la
// flecha sola no se lo dice (documento 3, sección 3.5).
function sentido(columna, ordenPor, ordenDireccion) {
    if (!columna.ordenable) {
        return undefined;
    }

    if (columna.clave !== ordenPor) {
        return "none";
    }

    return ordenDireccion === "desc" ? "descending" : "ascending";
}
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
                            :aria-sort="
                                sentido(columna, ordenPor, ordenDireccion)
                            "
                            class="sticky top-0 z-10 bg-marca px-3 py-2 text-apoyo font-semibold text-texto-invertido"
                            :class="columna.numerica ? 'text-right' : 'text-left'"
                        >
                            <button
                                v-if="columna.ordenable"
                                type="button"
                                class="inline-flex items-center gap-1"
                                @click="emit('ordenar', columna.clave)"
                            >
                                {{ columna.titulo }}
                                <span aria-hidden="true">{{
                                    columna.clave !== ordenPor
                                        ? "↕"
                                        : ordenDireccion === "desc"
                                          ? "↓"
                                          : "↑"
                                }}</span>
                            </button>
                            <template v-else>{{ columna.titulo }}</template>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="fila in filas"
                        :key="fila[clave]"
                        :tabindex="clicable ? 0 : undefined"
                        class="h-fila border-b border-borde"
                        :class="
                            clicable
                                ? 'cursor-pointer hover:bg-superficie-resaltada'
                                : ''
                        "
                        @click="clicable && emit('abrir', fila)"
                        @keydown.enter="clicable && emit('abrir', fila)"
                        @keydown.space.prevent="clicable && emit('abrir', fila)"
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
