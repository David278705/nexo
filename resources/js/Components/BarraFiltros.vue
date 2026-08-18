<script setup>
/*
 * La barra de filtros de los siete listados. Los controles van en el slot por
 * defecto; los filtros ya aplicados se ven debajo como etiquetas removibles
 * (documento 3, sección 3.4 y regla de interfaz).
 *
 * Cada filtro aplicado es `{ clave, texto }`. Quitar uno emite `quitar` con su
 * clave: el listado vuelve a pedir la página al servidor, no filtra aquí.
 */
defineProps({
    aplicados: { type: Array, default: () => [] },
});

const emit = defineEmits(["quitar", "limpiar"]);
</script>

<template>
    <div class="mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <slot />
        </div>

        <div
            v-if="aplicados.length > 0"
            class="mt-3 flex flex-wrap items-center gap-2"
        >
            <span class="text-apoyo text-texto-secundario">
                Filtros aplicados:
            </span>

            <span
                v-for="filtro in aplicados"
                :key="filtro.clave"
                class="inline-flex items-center gap-2 rounded-full bg-informacion-fondo px-3 py-1 text-apoyo font-medium text-informacion-texto"
            >
                {{ filtro.texto }}
                <button
                    type="button"
                    class="text-accion"
                    :aria-label="`Quitar el filtro ${filtro.texto}`"
                    @click="emit('quitar', filtro.clave)"
                >
                    ✕
                </button>
            </span>

            <button
                type="button"
                class="text-apoyo font-medium text-accion hover:underline"
                @click="emit('limpiar')"
            >
                Limpiar todo
            </button>
        </div>
    </div>
</template>
