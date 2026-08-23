<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Modal from "@/Components/Modal.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * El panel de alertas (documento 2, sección 5.5).
 *
 * Es el registro de envíos y, a la vez, el canal alterno del correo: la tabla
 * responde «¿se mandó, a quién y con qué resultado?» y el detalle enseña el
 * aviso entero, con el mismo texto que salió del buzón.
 *
 * No tiene botón de exportar ni de crear nada, y por eso `useListado` se declara
 * sin `pagina`: aquí no se registra, se consulta. Tampoco lleva la fila a una
 * ficha —no hay dirección de detalle— porque todo lo que se enseñaría ya viaja
 * con la fila; abrirla despliega el modal.
 */
const props = defineProps({
    alertas: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
});

const columnas = [
    { clave: "programada", titulo: "Programada", ordenable: true },
    { clave: "enviada", titulo: "Enviada", ordenable: true },
    { clave: "tipo", titulo: "Alerta", ordenable: true },
    { clave: "empleado", titulo: "Sobre" },
    { clave: "destinatarios", titulo: "Destinatarios" },
    { clave: "estado", titulo: "Estado" },
    { clave: "intentos", titulo: "Intentos", numerica: true },
];

const { pedir, ordenar, quitar, limpiar } = useListado({
    ruta: "/alertas",
    filtros: () => props.filtros,
    tieneFiltros: () => props.tieneFiltros,
    deLista: ["tipo", "estado"],
});

const tipos = computed(() => [
    { valor: TODOS, texto: "Todas las alertas" },
    ...props.opciones.tipos,
]);

const estados = computed(() => [
    { valor: TODOS, texto: "Todos los estados" },
    ...props.opciones.estados,
]);

// El detalle es la fila que se pulsó, sin ninguna vuelta al servidor.
const detalle = ref(null);

const datosDelDetalle = computed(() =>
    detalle.value === null
        ? []
        : [
              { etiqueta: "Alerta", valor: detalle.value.tipo },
              { etiqueta: "Sobre", valor: detalle.value.empleado },
              { etiqueta: "Fecha programada", valor: detalle.value.programada },
              { etiqueta: "Fecha de envío", valor: detalle.value.enviada },
              { etiqueta: "Estado", valor: detalle.value.estado },
              { etiqueta: "Intentos", valor: String(detalle.value.intentos) },
              {
                  etiqueta: "Destinatarios",
                  valor: detalle.value.destinatarios.join(", "),
              },
          ],
);
</script>

<template>
    <Head title="Alertas" />

    <Armazon>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">Alertas</h1>
                <p class="mt-1 text-apoyo text-texto-secundario">
                    Todo lo que el sistema te ha enviado por correo. Si un aviso
                    no llegó a tu bandeja, aquí puedes leerlo completo.
                </p>
            </div>
        </div>

        <div class="mt-5">
            <BarraFiltros
                :aplicados="aplicados"
                @quitar="quitar"
                @limpiar="limpiar"
            >
                <div class="min-w-[160px]">
                    <CampoFecha
                        :model-value="filtros.desde"
                        etiqueta="Desde"
                        opcional
                        @update:model-value="pedir({ desde: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[160px]">
                    <CampoFecha
                        :model-value="filtros.hasta"
                        etiqueta="Hasta"
                        opcional
                        @update:model-value="pedir({ hasta: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[260px]">
                    <Selector
                        :model-value="filtros.tipo"
                        etiqueta="Alerta"
                        :opciones="tipos"
                        :con-marcador="false"
                        @update:model-value="pedir({ tipo: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.estado"
                        etiqueta="Estado"
                        :opciones="estados"
                        :con-marcador="false"
                        @update:model-value="pedir({ estado: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="alertas.filas"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Todavía no te hemos enviado ninguna alerta."
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos envíos con estos filtros.'
                        : 'Cuando un contrato o un periodo de prueba se acerque a su plazo, el aviso saldrá por correo y quedará aquí.'
                "
                @ordenar="ordenar"
                @abrir="detalle = $event"
            >
                <template #celda-destinatarios="{ fila }">
                    {{ fila.destinatarios.join(", ") }}
                </template>

                <template #celda-estado="{ fila }">
                    <Etiqueta :tono="fila.tono">{{ fila.estado }}</Etiqueta>
                </template>

                <template #accion-vacio>
                    <Boton
                        v-if="tieneFiltros"
                        tipo="button"
                        variante="secundario"
                        @click="limpiar"
                    >
                        Quitar filtros
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="alertas.total > 0"
                :pagina="alertas.pagina"
                :paginas="alertas.paginas"
                :total="alertas.total"
                :desde="alertas.desde"
                :hasta="alertas.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>

        <Modal
            :abierto="detalle !== null"
            :titulo="detalle?.asunto ?? ''"
            ancho="ancho"
            @cerrar="detalle = null"
        >
            <template v-if="detalle !== null">
                <!--
                    El aviso, con el mismo texto que salió por correo. Es la
                    mitad de «canal alterno»: la otra mitad, saber si se envió,
                    está en los datos de abajo.
                -->
                <p
                    v-for="(parrafo, indice) in detalle.parrafos"
                    :key="indice"
                    class="mb-2 text-cuerpo text-texto"
                >
                    {{ parrafo }}
                </p>

                <Aviso v-if="detalle.motivo !== ''" tono="error" class="mt-4">
                    No se pudo entregar: {{ detalle.motivo }}
                </Aviso>

                <div class="mt-5">
                    <ListaDeDatos titulo="Envío" :campos="datosDelDetalle" />
                </div>
            </template>

            <template #pie>
                <Boton
                    tipo="button"
                    variante="secundario"
                    @click="detalle = null"
                >
                    Cerrar
                </Boton>
            </template>
        </Modal>
    </Armazon>
</template>
