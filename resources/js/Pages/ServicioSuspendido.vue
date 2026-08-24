<script setup>
import { router } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import Boton from "@/Components/Boton.vue";

/*
 * La pantalla de un servicio suspendido (documento 4, capítulo 6).
 *
 * Es la única que ve un usuario de una empresa suspendida, y no tiene el
 * armazón con el menú a propósito: un menú cuyos once enlaces devuelven a esta
 * misma pantalla sería una burla. Se usa el panel de las pantallas de acceso,
 * que es el que ya sabe verse solo, sin barra lateral.
 *
 * Los tres puntos llegan escritos del servidor porque son una promesa del
 * producto —y de la sección A.4 del documento 6— y no un texto de relleno: la
 * misma prueba que comprueba el bloqueo lee que se le dice al cliente que su
 * información sigue ahí y que puede pedirla.
 */
defineProps({
    empresa: { type: String, required: true },
    titulo: { type: String, required: true },
    explicacion: { type: String, required: true },
    puntos: { type: Array, required: true },
});

function salir() {
    router.post("/salir");
}
</script>

<template>
    <PanelDeAcceso :titulo="titulo" :descripcion="explicacion">
        <p class="text-sm font-semibold text-texto">{{ empresa }}</p>

        <ul class="mt-4 space-y-3">
            <li
                v-for="punto in puntos"
                :key="punto"
                class="flex gap-2 text-sm text-texto-secundario"
            >
                <span aria-hidden="true" class="text-texto-terciario">•</span>
                <span>{{ punto }}</span>
            </li>
        </ul>

        <p class="mt-6 text-sm text-texto-secundario">
            Para reactivarlo, habla con quien te presta el servicio.
        </p>

        <div class="mt-6">
            <Boton tipo="button" variante="secundario" ancho @click="salir">
                Cerrar sesión
            </Boton>
        </div>
    </PanelDeAcceso>
</template>
