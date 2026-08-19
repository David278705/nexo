<script setup>
import { computed } from "vue";

/*
 * La paginación de los siete listados (documento 3, sección 6.1): 25 filas por
 * página, con opción de 50 y 100.
 *
 * No decide nada. Emite la página o el tamaño elegidos y el listado se los pide
 * al servidor, que es donde vive el filtrado.
 */
const props = defineProps({
    pagina: { type: Number, required: true },
    paginas: { type: Number, required: true },
    total: { type: Number, required: true },
    // El primero y el último registro de la página, tal como los cuenta el
    // servidor. Van vacíos cuando la página no trae nada.
    desde: { type: Number, default: null },
    hasta: { type: Number, default: null },
    porPagina: { type: Number, required: true },
    tamanos: { type: Array, default: () => [25, 50, 100] },
});

const emit = defineEmits(["ir", "tamano"]);

const resumen = computed(() => {
    if (props.total === 0) {
        return "Sin registros";
    }

    return `Mostrando ${props.desde} a ${props.hasta} de ${props.total}`;
});

const hayAnterior = computed(() => props.pagina > 1);
const haySiguiente = computed(() => props.pagina < props.paginas);
</script>

<template>
    <div
        class="mt-3 flex flex-wrap items-center justify-between gap-3 text-apoyo text-texto-secundario"
    >
        <p>{{ resumen }}</p>

        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2">
                Filas por página
                <select
                    :value="porPagina"
                    class="h-control rounded-[var(--radius-caja)] border border-borde bg-superficie px-2 text-tabla text-texto"
                    @change="emit('tamano', Number($event.target.value))"
                >
                    <option
                        v-for="tamano in tamanos"
                        :key="tamano"
                        :value="tamano"
                    >
                        {{ tamano }}
                    </option>
                </select>
            </label>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="h-control rounded-[var(--radius-caja)] border border-borde bg-superficie px-3 text-tabla text-texto disabled:cursor-not-allowed disabled:opacity-45"
                    :disabled="!hayAnterior"
                    @click="emit('ir', pagina - 1)"
                >
                    Anterior
                </button>

                <span class="text-texto">
                    Página {{ pagina }} de {{ Math.max(paginas, 1) }}
                </span>

                <button
                    type="button"
                    class="h-control rounded-[var(--radius-caja)] border border-borde bg-superficie px-3 text-tabla text-texto disabled:cursor-not-allowed disabled:opacity-45"
                    :disabled="!haySiguiente"
                    @click="emit('ir', pagina + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
