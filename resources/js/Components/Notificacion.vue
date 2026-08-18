<script setup>
import { onBeforeUnmount, ref, watch } from "vue";

/*
 * Notificación de confirmación (documento 3, sección 3.4): aparece arriba a la
 * derecha y desaparece sola a los cuatro segundos. Es la respuesta a «guardé y
 * no pasó nada visible» de la sección 2.5.
 */
const SEGUNDOS_VISIBLE = 4000;

const props = defineProps({
    mensaje: { type: String, default: "" },
    duracion: { type: Number, default: SEGUNDOS_VISIBLE },
});

const emit = defineEmits(["cerrar"]);

const visible = ref(false);
let temporizador = null;

function detener() {
    if (temporizador !== null) {
        clearTimeout(temporizador);
        temporizador = null;
    }
}

watch(
    () => props.mensaje,
    (mensaje) => {
        detener();
        visible.value = mensaje !== "";

        if (!visible.value) {
            return;
        }

        temporizador = setTimeout(() => {
            visible.value = false;
            emit("cerrar");
        }, props.duracion);
    },
    { immediate: true },
);

onBeforeUnmount(detener);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="visible"
            role="status"
            aria-live="polite"
            class="fixed right-4 top-4 z-50 rounded-[var(--radius-caja)] bg-marca px-4 py-3 text-tabla text-texto-invertido shadow-flotante"
        >
            {{ mensaje }}
        </div>
    </Teleport>
</template>
