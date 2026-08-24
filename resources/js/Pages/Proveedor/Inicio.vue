<script setup>
import { Head } from "@inertiajs/vue3";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import TarjetaDeAccion from "@/Components/TarjetaDeAccion.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * El inicio del panel: «el estado del negocio y lo que requiere atención hoy»
 * (documento 4, capítulo 3), en ese orden.
 *
 * Las seis tarjetas de atención y los siete indicadores de negocio llegan ya
 * calculados de `App\Proveedor\PanelDeInicio`: la pantalla no cuenta ni
 * decide nada, solo los pinta. `TarjetaDeAccion` es el mismo componente del
 * bloque de acción del cliente (documento 3, 5.2) —una cifra que enlaza a
 * donde se puede actuar— y `TarjetaIndicador` el de las cifras de negocio,
 * que ya usa la consola de alertas del proveedor.
 */
defineProps({
    fecha: { type: String, required: true },
    atencion: { type: Array, required: true },
    negocio: { type: Array, required: true },
});
</script>

<template>
    <Head title="Panel del proveedor" />

    <ArmazonProveedor>
        <h1 class="text-titulo font-semibold text-texto">
            Estado del negocio
        </h1>
        <p class="mt-1 text-apoyo text-texto-secundario">{{ fecha }}</p>

        <h2 class="mt-6 text-cuerpo font-semibold text-texto">
            Requiere atención hoy
        </h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <TarjetaDeAccion
                v-for="tarjeta in atencion"
                :key="tarjeta.etiqueta"
                :valor="tarjeta.valor"
                :etiqueta="tarjeta.etiqueta"
                :enlace="tarjeta.enlace"
                :accion="tarjeta.accion"
                :tono="tarjeta.tono"
            />
        </div>

        <h2 class="mt-8 text-cuerpo font-semibold text-texto">Negocio</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TarjetaIndicador
                v-for="tarjeta in negocio"
                :key="tarjeta.etiqueta"
                :valor="tarjeta.valor"
                :etiqueta="tarjeta.etiqueta"
                :variacion="tarjeta.variacion"
                :tendencia="tarjeta.tendencia"
                :nota="tarjeta.nota"
                :nota-tono="tarjeta.notaTono"
            />
        </div>
    </ArmazonProveedor>
</template>
