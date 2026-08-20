<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Boton from "@/Components/Boton.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import ModalDeRenovacion from "@/Components/ModalDeRenovacion.vue";
import TablaDatos from "@/Components/TablaDatos.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * El detalle del contrato (documento 3, sección 5.4).
 *
 * Arriba, el contrato vigente en tarjetas, con **la fecha límite del preaviso
 * destacada**: el documento 1, 5.5, pide que se vea aquí y no solo que llegue
 * por correo. Debajo, el historial completo empezando por el periodo inicial.
 *
 * La tabla del historial es la misma que enseña la pestaña de contrato de la
 * ficha del empleado, con las mismas columnas y armada por el mismo método del
 * servidor. Es una tabla que informa, no que lleve a otra parte, y por eso no
 * es clicable.
 */
const props = defineProps({
    contrato: { type: Object, required: true },
    indicadores: { type: Array, default: () => [] },
    campos: { type: Array, default: () => [] },
    historial: { type: Array, default: () => [] },
    renovacion: { type: Object, default: () => ({}) },
});

/*
 * «Renovar contrato» solo existe en el término fijo (documento 1, 5.5). El botón
 * de «Cambiar a término indefinido» va al lado y llega con F1-11: no se pone un
 * botón que no lleve a ninguna parte.
 */
const renovando = ref(false);

const urlDelContrato = `/contratos/${props.contrato.empleadoId}`;

const columnasHistorial = [
    { clave: "orden", titulo: "#", numerica: true },
    { clave: "evento", titulo: "Evento" },
    { clave: "contrato", titulo: "Tipo de contrato" },
    { clave: "desde", titulo: "Desde" },
    { clave: "hasta", titulo: "Hasta" },
];
</script>

<template>
    <Head :title="`Contrato de ${contrato.empleado}`" />

    <Armazon>
        <Link href="/contratos" class="text-apoyo text-accion">
            ← Volver a contratos
        </Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Contrato de {{ contrato.empleado }}
                </h1>
                <p
                    class="mt-1 flex flex-wrap items-center gap-2 text-apoyo text-texto-secundario"
                >
                    <span>{{ contrato.cargo }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ contrato.tipo }}</span>
                    <Etiqueta :tono="contrato.tono">
                        {{ contrato.estado }}
                    </Etiqueta>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <Link
                    :href="`/empleados/${contrato.empleadoId}`"
                    class="text-apoyo text-accion"
                >
                    Ver la ficha del empleado
                </Link>

                <Boton
                    v-if="contrato.admiteRenovacion && renovacion.disponible"
                    tipo="button"
                    @click="renovando = true"
                >
                    Renovar contrato
                </Boton>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TarjetaIndicador
                v-for="indicador in indicadores"
                :key="indicador.etiqueta"
                :valor="indicador.valor"
                :etiqueta="indicador.etiqueta"
                :nota="indicador.nota"
                :nota-tono="indicador.tono"
            />
        </div>

        <div class="mt-5 grid gap-4">
            <div class="lg:max-w-xl">
                <ListaDeDatos titulo="Contrato vigente" :campos="campos" />
            </div>

            <div>
                <h2 class="mb-2 text-seccion font-semibold text-texto">
                    Historial de periodos
                </h2>
                <p class="mb-2 text-apoyo text-texto-secundario">
                    Empieza por el contrato inicial. Cada prórroga y cada cambio
                    de modalidad agregan una fila.
                </p>
                <TablaDatos
                    :columnas="columnasHistorial"
                    :filas="historial"
                    :clicable="false"
                    titulo-vacio="Este contrato aún no tiene historial."
                />
            </div>
        </div>

        <ModalDeRenovacion
            v-if="renovacion.disponible"
            :abierto="renovando"
            :url="urlDelContrato"
            :renovacion="renovacion"
            @cerrar="renovando = false"
        />
    </Armazon>
</template>
