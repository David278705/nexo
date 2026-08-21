<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import Armazon from "@/Components/Armazon.vue";

/*
 * El armazón del panel del proveedor (documento 4, capítulo 2, y
 * `prototipos/panel-proveedor.html`).
 *
 * Es el mismo armazón del cliente con otras secciones, otro rótulo y el azul
 * oscuro de la marca: la forma es idéntica —barra lateral fija y franja
 * superior—, así que duplicarla habría sido mantener dos veces el mismo menú
 * replegable.
 *
 * Las secciones llegan en la prop compartida `secciones`, que el servidor manda
 * solo al `owner`. Este componente no decide quién entra: eso ya lo respondió
 * el middleware `proveedor` con un 403.
 */
const secciones = computed(() => usePage().props.secciones ?? []);
</script>

<template>
    <Armazon
        :secciones="secciones"
        titulo="Panel del proveedor"
        subtitulo="Administración y operación"
        encabezado="Panel del proveedor"
        oscuro
    >
        <slot />
    </Armazon>
</template>
