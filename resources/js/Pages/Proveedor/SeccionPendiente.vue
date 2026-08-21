<script setup>
import { Head } from "@inertiajs/vue3";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";

/*
 * Las ocho secciones del panel que todavía no tienen contenido. Igual que en el
 * entorno del cliente, existen para que el menú se pueda recorrer entero: la
 * dirección y el resaltado son los definitivos.
 *
 * La descripción no es un texto de relleno: es la de la tabla del capítulo 2 del
 * documento 4, y dice qué va a vivir aquí. La escribe el servidor, en
 * `SeccionDelPanel::descripcion()`, para que el menú y la pantalla no puedan
 * decir cosas distintas de la misma sección.
 */
defineProps({
    titulo: { type: String, required: true },
    descripcion: { type: String, required: true },
});
</script>

<template>
    <Head :title="titulo" />

    <ArmazonProveedor>
        <div>
            <h1 class="text-titulo font-semibold text-texto">{{ titulo }}</h1>
            <p class="mt-1 text-apoyo text-texto-secundario">
                {{ descripcion }}
            </p>
        </div>

        <div
            class="mt-4 rounded-[var(--radius-modal)] border border-borde bg-superficie"
        >
            <EstadoVacio
                titulo="Esta sección todavía no tiene contenido"
                descripcion="El menú, la puerta del panel y la navegación ya funcionan. Lo de dentro llega en su propia tarea."
            />
        </div>
    </ArmazonProveedor>
</template>
