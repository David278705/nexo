<script setup>
import { onBeforeUnmount, onMounted, watch } from "vue";

/*
 * Modal del sistema (documento 3, sección 3.4): 560 px para formularios cortos
 * y 800 px para el registro de novedades. Se cierra con Escape. En pantallas
 * pequeñas ocupa la pantalla completa.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    titulo: { type: String, required: true },
    ancho: {
        type: String,
        default: "corto",
        validator: (valor) => ["corto", "ancho"].includes(valor),
    },
});

const emit = defineEmits(["cerrar"]);

function alPulsarTecla(evento) {
    if (evento.key === "Escape" && props.abierto) {
        emit("cerrar");
    }
}

// El fondo no se desplaza mientras el modal está encima.
watch(
    () => props.abierto,
    (abierto) => {
        if (typeof document !== "undefined") {
            document.body.style.overflow = abierto ? "hidden" : "";
        }
    },
);

onMounted(() => document.addEventListener("keydown", alPulsarTecla));

onBeforeUnmount(() => {
    document.removeEventListener("keydown", alPulsarTecla);

    if (typeof document !== "undefined") {
        document.body.style.overflow = "";
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="abierto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-marca-oscuro/45 p-0 lg:p-6"
            @click.self="emit('cerrar')"
        >
            <div
                role="dialog"
                aria-modal="true"
                :aria-label="titulo"
                class="flex h-full w-full flex-col overflow-hidden bg-superficie shadow-flotante lg:h-auto lg:max-h-[90vh] lg:rounded-[var(--radius-modal)]"
                :class="
                    ancho === 'ancho'
                        ? 'lg:max-w-modal-ancho'
                        : 'lg:max-w-modal-corto'
                "
            >
                <header
                    class="flex items-center justify-between border-b border-borde px-5 py-4"
                >
                    <h2 class="text-seccion font-semibold text-texto">
                        {{ titulo }}
                    </h2>
                    <button
                        type="button"
                        aria-label="Cerrar"
                        class="text-texto-terciario hover:text-texto"
                        @click="emit('cerrar')"
                    >
                        ✕
                    </button>
                </header>

                <div class="flex-1 overflow-auto px-5 py-5 text-cuerpo">
                    <slot />
                </div>

                <footer
                    v-if="$slots.pie"
                    class="flex justify-end gap-3 border-t border-borde bg-fondo px-5 py-4"
                >
                    <slot name="pie" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
