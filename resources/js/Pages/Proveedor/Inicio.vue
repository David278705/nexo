<script setup>
import { Head } from "@inertiajs/vue3";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";

/*
 * El inicio del panel: «el estado del negocio y lo que requiere atención hoy»
 * (documento 4, capítulo 3).
 *
 * Las seis tarjetas de atención y los siete indicadores de negocio no están
 * aquí, y no por olvido: se alimentan de las suscripciones, de la consola de
 * envíos y de las métricas de uso, que no existen todavía. Enseñarlos con ceros
 * o con números inventados sería peor que declarar el hueco, porque el panel del
 * negocio se revisa cada mes justamente para decidir con él.
 *
 * La fecha llega escrita desde el servidor, en la zona horaria de la aplicación.
 */
defineProps({
    fecha: { type: String, required: true },
});
</script>

<template>
    <Head title="Panel del proveedor" />

    <ArmazonProveedor>
        <h1 class="text-titulo font-semibold text-texto">
            Estado del negocio
        </h1>
        <p class="mt-1 text-apoyo text-texto-secundario">{{ fecha }}</p>

        <Aviso
            tono="informacion"
            titulo="El panel ya está de pie; los indicadores llegan con sus datos"
            class="mt-6"
        >
            Las tarjetas de atención y las cifras del negocio del capítulo 3 se
            alimentan de las suscripciones, de la consola de envíos y de las
            métricas de uso. Mientras esas tres no existan, aquí no se enseña
            ningún número: un indicador inventado en el panel del negocio es peor
            que un hueco declarado. Lo que sí funciona ya es la puerta —solo el
            proveedor, con segundo factor— y las nueve secciones del menú.
        </Aviso>
    </ArmazonProveedor>
</template>
