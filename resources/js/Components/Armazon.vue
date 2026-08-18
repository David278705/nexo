<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import BarraLateral from "@/Components/BarraLateral.vue";
import EncabezadoApp from "@/Components/EncabezadoApp.vue";

/*
 * El armazón del documento 3, sección 4.1: barra lateral fija a la izquierda y
 * el contenido a la derecha, con la franja superior encima.
 *
 * Toda pantalla del sistema se envuelve aquí. Lo que pinta el menú —la empresa,
 * el usuario y los módulos— llega en la prop compartida `usuario`, que sale de
 * la misma política que responde 403. Este componente no decide nada.
 */
const pagina = usePage();

const usuario = computed(() => pagina.props.usuario);
const modulos = computed(() => usuario.value?.modulos ?? []);
const urlActual = computed(() => pagina.url.split("?")[0]);

/*
 * Hasta 1024px la barra lateral no ocupa sitio: se abre por encima del
 * contenido desde el botón del encabezado (documento 3, sección 6.4).
 */
const menuAbierto = ref(false);

function cerrarConEscape(evento) {
    if (evento.key === "Escape") {
        menuAbierto.value = false;
    }
}

onMounted(() => document.addEventListener("keydown", cerrarConEscape));
onBeforeUnmount(() => document.removeEventListener("keydown", cerrarConEscape));

// Navegar cierra el menú desplegado: dejarlo abierto tapa la pantalla a la que
// se acaba de entrar.
watch(urlActual, () => {
    menuAbierto.value = false;
});
</script>

<template>
    <div class="flex min-h-screen bg-fondo">
        <!-- La barra fija, desde 1024px en adelante. -->
        <aside class="hidden w-[236px] shrink-0 lg:block">
            <div class="fixed inset-y-0 left-0 w-[236px]">
                <BarraLateral :modulos="modulos" :url-actual="urlActual" />
            </div>
        </aside>

        <!-- La misma barra, desplegada sobre el contenido en pantalla pequeña. -->
        <div v-if="menuAbierto" class="lg:hidden">
            <div
                class="fixed inset-0 z-40 bg-marca-oscuro/50"
                @click="menuAbierto = false"
            ></div>

            <div class="fixed inset-y-0 left-0 z-50 w-[236px] shadow-flotante">
                <BarraLateral
                    :modulos="modulos"
                    :url-actual="urlActual"
                    @navegar="menuAbierto = false"
                />
            </div>

            <button
                type="button"
                class="fixed left-[244px] top-3 z-50 rounded-[var(--radius-caja)] p-2 text-tabla text-texto-invertido"
                aria-label="Cerrar el menú"
                @click="menuAbierto = false"
            >
                <span aria-hidden="true">✕</span>
            </button>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <EncabezadoApp
                v-if="usuario"
                :empresa="usuario.empresa ?? ''"
                :nit="usuario.nit ?? ''"
                :usuario="usuario"
                @abrir-menu="menuAbierto = true"
            />

            <main class="w-full max-w-contenido p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
