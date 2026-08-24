<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

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

const contenedor = ref(null);

// El elemento que tenía el foco antes de abrir, para devolvérselo al cerrar
// (documento 3, sección 3.5): quien abrió el modal con el teclado no puede
// perder su sitio en la pantalla de atrás.
let elementoActivoAlAbrir = null;

const SELECTOR_FOCABLES =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function elementosFocables() {
    if (!contenedor.value) {
        return [];
    }

    return [...contenedor.value.querySelectorAll(SELECTOR_FOCABLES)];
}

/*
 * El foco no sale del diálogo mientras está abierto: quien tabula desde el
 * último campo vuelve al primero, en vez de caer en la pantalla que el modal
 * tapa. Sin esto, «todo se puede recorrer con el teclado, con el foco
 * visible» (documento 3, sección 3.5) es falso apenas hay algo detrás.
 */
function atraparFoco(evento) {
    const focables = elementosFocables();

    if (focables.length === 0) {
        evento.preventDefault();
        return;
    }

    const primero = focables[0];
    const ultimo = focables[focables.length - 1];
    const activo = document.activeElement;
    const foraDelModal = !contenedor.value.contains(activo);

    if (evento.shiftKey && (activo === primero || foraDelModal)) {
        evento.preventDefault();
        ultimo.focus();
    } else if (!evento.shiftKey && (activo === ultimo || foraDelModal)) {
        evento.preventDefault();
        primero.focus();
    }
}

function alPulsarTecla(evento) {
    if (!props.abierto) {
        return;
    }

    if (evento.key === "Escape") {
        emit("cerrar");
    } else if (evento.key === "Tab") {
        atraparFoco(evento);
    }
}

watch(
    () => props.abierto,
    async (abierto) => {
        // El fondo no se desplaza mientras el modal está encima.
        if (typeof document !== "undefined") {
            document.body.style.overflow = abierto ? "hidden" : "";
        }

        if (abierto) {
            elementoActivoAlAbrir = document.activeElement;
            await nextTick();

            /*
             * Un formulario propio —el campo del documento de
             * `ModalDeNovedad`, por ejemplo— ya pudo llevarse el foco a
             * donde le corresponde. Solo se pone el primero de reserva
             * cuando nadie lo hizo, que es lo que le pasa al resto de
             * modales del sistema.
             */
            if (contenedor.value && !contenedor.value.contains(document.activeElement)) {
                elementosFocables()[0]?.focus();
            }
        } else if (
            elementoActivoAlAbrir &&
            typeof document !== "undefined" &&
            document.body.contains(elementoActivoAlAbrir)
        ) {
            elementoActivoAlAbrir.focus();
            elementoActivoAlAbrir = null;
        }
    },
    { immediate: true },
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
                ref="contenedor"
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
