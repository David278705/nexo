<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import BarraLateral from "@/Components/BarraLateral.vue";
import EncabezadoApp from "@/Components/EncabezadoApp.vue";
import FranjaDeVisita from "@/Components/FranjaDeVisita.vue";
import Notificacion from "@/Components/Notificacion.vue";

/*
 * El armazón del documento 3, sección 4.1: barra lateral fija a la izquierda y
 * el contenido a la derecha, con la franja superior encima.
 *
 * Toda pantalla del sistema se envuelve aquí. Lo que pinta el menú —la empresa,
 * el usuario y los módulos— llega en la prop compartida `usuario`, que sale de
 * la misma política que responde 403. Este componente no decide nada.
 *
 * El panel del proveedor tiene la misma forma con otras secciones y otro
 * rótulo, así que usa este mismo armazón pasándole las suyas. Sin las props, el
 * armazón es el del cliente: eso es lo que ve toda pantalla ya construida.
 */
const props = defineProps({
    // Las secciones del menú. Si no llegan, son los módulos del usuario.
    secciones: { type: Array, default: null },
    titulo: { type: String, default: undefined },
    subtitulo: { type: String, default: undefined },
    // El texto de la franja superior. Si no llega, es la empresa del usuario.
    encabezado: { type: String, default: null },
    oscuro: { type: Boolean, default: false },
});

const pagina = usePage();

const usuario = computed(() => pagina.props.usuario);

/*
 * El mensaje que dejó la petición anterior (`->with('estado', …)`), pintado en
 * un solo sitio para las dos aplicaciones. Sin esto, un `with('estado')` del
 * servidor no se veía en ninguna parte y la respuesta a «guardé y no pasó nada
 * visible» del documento 3, sección 2.5, quedaba a medias.
 */
const estado = computed(() => pagina.props.estado ?? "");

/*
 * La visita del proveedor al entorno de este cliente (documento 4, 4.4). Es
 * nula en toda petición normal: la franja solo existe mientras el proveedor
 * está dentro, y el cliente no la ve nunca.
 */
const visita = computed(() => pagina.props.visita ?? null);
const modulos = computed(() => props.secciones ?? usuario.value?.modulos ?? []);
const urlActual = computed(() => pagina.url.split("?")[0]);

/*
 * Hasta 1024px la barra lateral no ocupa sitio: se abre por encima del
 * contenido desde el botón del encabezado (documento 3, sección 6.4).
 */
const menuAbierto = ref(false);
const panelDesplegado = ref(null);

// El mismo mecanismo de foco que `Modal.vue` (F6-06, documento 3, 3.5): sin
// él, este menú era el único diálogo del sistema que dejaba escapar el Tab a
// la pantalla de atrás y no devolvía el foco a quien lo abrió.
const SELECTOR_FOCABLES =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

let elementoActivoAlAbrir = null;

function elementosFocables() {
    if (!panelDesplegado.value) {
        return [];
    }

    return [...panelDesplegado.value.querySelectorAll(SELECTOR_FOCABLES)];
}

function atraparFoco(evento) {
    const focables = elementosFocables();

    if (focables.length === 0) {
        evento.preventDefault();
        return;
    }

    const primero = focables[0];
    const ultimo = focables[focables.length - 1];
    const activo = document.activeElement;
    const foraDelPanel = !panelDesplegado.value.contains(activo);

    if (evento.shiftKey && (activo === primero || foraDelPanel)) {
        evento.preventDefault();
        ultimo.focus();
    } else if (!evento.shiftKey && (activo === ultimo || foraDelPanel)) {
        evento.preventDefault();
        primero.focus();
    }
}

function alPulsarTecla(evento) {
    if (!menuAbierto.value) {
        return;
    }

    if (evento.key === "Escape") {
        menuAbierto.value = false;
    } else if (evento.key === "Tab") {
        atraparFoco(evento);
    }
}

onMounted(() => document.addEventListener("keydown", alPulsarTecla));
onBeforeUnmount(() => document.removeEventListener("keydown", alPulsarTecla));

watch(menuAbierto, async (abierto) => {
    if (abierto) {
        elementoActivoAlAbrir = document.activeElement;
        await nextTick();
        elementosFocables()[0]?.focus();
    } else if (
        elementoActivoAlAbrir &&
        typeof document !== "undefined" &&
        document.body.contains(elementoActivoAlAbrir)
    ) {
        elementoActivoAlAbrir.focus();
        elementoActivoAlAbrir = null;
    }
});

// Navegar cierra el menú desplegado: dejarlo abierto tapa la pantalla a la que
// se acaba de entrar.
watch(urlActual, () => {
    menuAbierto.value = false;
});
</script>

<template>
    <div class="flex min-h-screen bg-fondo">
        <Notificacion :mensaje="estado" />

        <!-- La barra fija, desde 1024px en adelante. -->
        <aside class="hidden w-[236px] shrink-0 lg:block">
            <div class="fixed inset-y-0 left-0 w-[236px]">
                <BarraLateral
                    :modulos="modulos"
                    :url-actual="urlActual"
                    :titulo="titulo"
                    :subtitulo="subtitulo"
                    :oscuro="oscuro"
                />
            </div>
        </aside>

        <!-- La misma barra, desplegada sobre el contenido en pantalla pequeña. -->
        <div
            v-if="menuAbierto"
            ref="panelDesplegado"
            class="lg:hidden"
            role="dialog"
            aria-modal="true"
            aria-label="Menú"
        >
            <div
                class="fixed inset-0 z-40 bg-marca-oscuro/50"
                @click="menuAbierto = false"
            ></div>

            <div class="fixed inset-y-0 left-0 z-50 w-[236px] shadow-flotante">
                <BarraLateral
                    :modulos="modulos"
                    :url-actual="urlActual"
                    :titulo="titulo"
                    :subtitulo="subtitulo"
                    :oscuro="oscuro"
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
            <FranjaDeVisita v-if="visita" :visita="visita" />

            <EncabezadoApp
                v-if="usuario"
                :empresa="encabezado ?? usuario.empresa ?? ''"
                :nit="encabezado ? '' : (usuario.nit ?? '')"
                :usuario="usuario"
                @abrir-menu="menuAbierto = true"
            />

            <main class="w-full max-w-contenido p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
