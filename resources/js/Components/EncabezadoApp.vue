<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { router } from "@inertiajs/vue3";

/*
 * La franja superior del documento 3, sección 4.3: el nombre de la empresa a la
 * izquierda —para que nunca haya duda de dónde se está trabajando— y a la
 * derecha el usuario con su menú de perfil y cierre de sesión.
 *
 * El botón de menú solo existe por debajo de 1024px, donde la barra lateral se
 * repliega (documento 3, sección 6.4).
 */
const props = defineProps({
    empresa: { type: String, default: "" },
    nit: { type: String, default: "" },
    usuario: { type: Object, required: true },
});

const emitir = defineEmits(["abrir-menu"]);

const abierto = ref(false);
const contenedor = ref(null);

/*
 * Las iniciales del avatar. Nombre y primer apellido bastan: con tres o cuatro
 * palabras el círculo deja de leerse.
 */
const iniciales = computed(() =>
    (props.usuario.nombre ?? "")
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((palabra) => palabra.charAt(0).toUpperCase())
        .join(""),
);

// Cerrar sesión es un POST, no un enlace: un GET que cierra la sesión lo
// dispara cualquier cosa que precargue direcciones.
function salir() {
    abierto.value = false;
    router.post("/salir");
}

function cerrarAlPulsarFuera(evento) {
    if (contenedor.value && !contenedor.value.contains(evento.target)) {
        abierto.value = false;
    }
}

function cerrarConEscape(evento) {
    if (evento.key === "Escape") {
        abierto.value = false;
    }
}

onMounted(() => {
    document.addEventListener("mousedown", cerrarAlPulsarFuera);
    document.addEventListener("keydown", cerrarConEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", cerrarAlPulsarFuera);
    document.removeEventListener("keydown", cerrarConEscape);
});
</script>

<template>
    <header
        class="flex h-[54px] shrink-0 items-center justify-between gap-4 border-b border-borde bg-superficie px-4 lg:px-6"
    >
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="-ml-1 rounded-[var(--radius-caja)] p-2 text-texto-secundario hover:bg-superficie-resaltada lg:hidden"
                aria-label="Abrir el menú"
                @click="emitir('abrir-menu')"
            >
                <span aria-hidden="true" class="block text-cuerpo">☰</span>
            </button>

            <p class="min-w-0 truncate text-tabla font-semibold text-texto">
                {{ empresa }}
                <span
                    v-if="nit"
                    class="ml-2 text-apoyo font-normal text-texto-terciario"
                >
                    NIT {{ nit }}
                </span>
            </p>
        </div>

        <div ref="contenedor" class="relative shrink-0">
            <button
                type="button"
                class="flex items-center gap-2 rounded-[var(--radius-caja)] p-1 text-apoyo text-texto-secundario hover:bg-superficie-resaltada"
                :aria-expanded="abierto"
                aria-haspopup="menu"
                @click="abierto = !abierto"
            >
                <span class="hidden sm:inline">{{ usuario.nombre }}</span>
                <span
                    class="flex h-[29px] w-[29px] items-center justify-center rounded-full bg-accion text-apoyo font-semibold text-texto-invertido"
                    aria-hidden="true"
                >
                    {{ iniciales }}
                </span>
            </button>

            <div
                v-if="abierto"
                role="menu"
                class="absolute right-0 z-30 mt-2 w-56 rounded-[var(--radius-modal)] border border-borde bg-superficie p-1 shadow-flotante"
            >
                <div class="border-b border-borde px-3 py-2">
                    <p class="truncate text-tabla font-medium text-texto">
                        {{ usuario.nombre }}
                    </p>
                    <p class="truncate text-apoyo text-texto-secundario">
                        {{ usuario.correo }}
                    </p>
                </div>

                <button
                    type="button"
                    role="menuitem"
                    class="mt-1 flex w-full items-center rounded-[var(--radius-caja)] px-3 py-2 text-left text-tabla text-texto hover:bg-superficie-resaltada"
                    @click="salir"
                >
                    Cerrar sesión
                </button>
            </div>
        </div>
    </header>
</template>
