<script setup>
/*
 * El avance visible de un asistente por pasos (documento 3, sección 5.3 y
 * prototipo `sistema.html`).
 *
 * Solo pinta dónde está el usuario: no decide si puede avanzar ni guarda nada.
 * Se puede retroceder pulsando un paso ya recorrido —el documento lo pide
 * expresamente—, pero no saltar hacia adelante: los pasos siguientes se
 * anuncian como deshabilitados en vez de desaparecer, para que se vea desde el
 * principio cuánto falta.
 *
 * Vive en `Components/` y no dentro de la pantalla de empleados porque el panel
 * del proveedor construye otro asistente igual para crear empresas (F1B-02).
 */
defineProps({
    // Los títulos, en orden: `['Identificación', 'Residencia…', …]`.
    titulos: { type: Array, required: true },
    // Empieza en 0.
    actual: { type: Number, required: true },
});

const emit = defineEmits(["ir"]);
</script>

<template>
    <ol class="mt-4 flex flex-wrap gap-2" aria-label="Pasos del registro">
        <li
            v-for="(titulo, indice) in titulos"
            :key="titulo"
            class="min-w-[140px] flex-1"
            :aria-current="indice === actual ? 'step' : undefined"
        >
            <button
                type="button"
                :disabled="indice > actual"
                class="w-full border-t-2 pt-2 text-left text-apoyo transition-colors disabled:cursor-not-allowed"
                :class="[
                    indice === actual
                        ? 'border-accion font-semibold text-accion'
                        : '',
                    indice < actual ? 'border-exito text-texto-secundario' : '',
                    indice > actual ? 'border-borde text-texto-terciario' : '',
                ]"
                @click="emit('ir', indice)"
            >
                <span class="block text-[11px] uppercase tracking-wide">
                    Paso {{ indice + 1 }}
                    <span v-if="indice < actual"> · listo</span>
                </span>
                {{ titulo }}
            </button>
        </li>
    </ol>
</template>
