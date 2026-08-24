<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * El listado de empresas cliente (documento 4, 4.1).
 *
 * Es la pantalla desde la que se mira el negocio entero. Como los listados del
 * entorno del cliente, aquí no se filtra nada: cada cambio vuelve a pedir la
 * página al servidor con los filtros en la dirección.
 *
 * Las ocho columnas y los cuatro filtros del documento están completos desde
 * F5-04: «Pago» sale de la suscripción de cada empresa y su filtro ya se
 * ofrece. Una empresa a la que no se le cobra todavía lo dice con palabras en su
 * celda, no con una raya.
 */
const props = defineProps({
    empresas: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    conteos: { type: Array, default: () => [] },
    accion: { type: Object, required: true },
});

const columnas = [
    { clave: "empresa", titulo: "Empresa", ordenable: true },
    { clave: "plan", titulo: "Plan", ordenable: true },
    { clave: "empleados", titulo: "Empleados", numerica: true },
    { clave: "usuarios", titulo: "Usuarios", numerica: true, ordenable: true },
    { clave: "uso", titulo: "Uso" },
    { clave: "ingreso", titulo: "Último ingreso", ordenable: true },
    { clave: "pago", titulo: "Pago" },
    { clave: "estado", titulo: "Estado", ordenable: true },
];

// Esta pantalla no declara `pagina` porque no tiene exportación: el documento 4
// no la pide, y el composable no le arma una dirección que no existe.
const { busqueda, buscar, pedir, ordenar, quitar, limpiar, abrir } = useListado({
    ruta: "/proveedor/empresas",
    filtros: () => props.filtros,
    // El plan es texto libre —el nombre del plan—, así que ese vuelve a vacío.
    deLista: ["estado", "salud", "pago"],
});

const estados = computed(() => [
    { valor: TODOS, texto: "Todos los estados" },
    ...props.opciones.estados,
]);

const saludes = computed(() => [
    { valor: TODOS, texto: "Toda la salud de uso" },
    ...props.opciones.saludes,
]);

const pagos = computed(() => [
    { valor: TODOS, texto: "Todo el estado de pago" },
    ...props.opciones.pagos,
]);

const planes = computed(() => [
    { valor: "", texto: "Todos los planes" },
    ...props.opciones.planes.map((plan) => ({ valor: plan, texto: plan })),
]);

// «12 activas · 1 suspendida · 2 en prueba», como el prototipo. Cada trozo
// llega escrito del servidor, con su singular y su plural: aquí solo se juntan.
const resumenDeEstados = computed(() =>
    props.conteos.map((conteo) => conteo.texto).join(" · "),
);
</script>

<template>
    <Head title="Empresas cliente" />

    <ArmazonProveedor>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Empresas cliente
                </h1>
                <p class="mt-1 text-apoyo text-texto-secundario">
                    {{ resumenDeEstados }}
                </p>
            </div>

            <Link :href="accion.url">
                <Boton tipo="button">{{ accion.texto }}</Boton>
            </Link>
        </div>

        <div class="mt-5">
            <BarraFiltros
                :aplicados="aplicados"
                @quitar="quitar"
                @limpiar="limpiar"
            >
                <div class="min-w-[250px]">
                    <CampoTexto
                        v-model="busqueda"
                        etiqueta="Buscar por nombre o NIT"
                        tipo="search"
                        @update:model-value="buscar"
                    />
                </div>

                <div class="min-w-[170px]">
                    <Selector
                        :model-value="filtros.estado"
                        etiqueta="Estado"
                        :opciones="estados"
                        :con-marcador="false"
                        @update:model-value="pedir({ estado: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.plan"
                        etiqueta="Plan"
                        :opciones="planes"
                        :con-marcador="false"
                        @update:model-value="pedir({ plan: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.salud"
                        etiqueta="Salud de uso"
                        :opciones="saludes"
                        :con-marcador="false"
                        @update:model-value="pedir({ salud: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[200px]">
                    <Selector
                        :model-value="filtros.pago"
                        etiqueta="Estado de pago"
                        :opciones="pagos"
                        :con-marcador="false"
                        @update:model-value="pedir({ pago: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="empresas.filas"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                :titulo-vacio="
                    tieneFiltros
                        ? 'No encontramos empresas con estos filtros.'
                        : 'Todavía no hay ninguna empresa cliente.'
                "
                :descripcion-vacio="
                    tieneFiltros
                        ? ''
                        : 'Aquí verás a todos los clientes, cómo usan el sistema y en qué estado está cada cuenta.'
                "
                @abrir="abrir"
                @ordenar="ordenar"
            >
                <template #celda-empresa="{ fila }">
                    <span class="font-semibold">{{ fila.empresa }}</span>
                    <span class="block text-apoyo text-texto-secundario">
                        NIT {{ fila.nit }}
                    </span>
                </template>

                <!--
                    El resaltado que pide el documento cuando los empleados
                    cargados superan los del plan: «es una conversación de
                    venta». Lo decide el servidor; aquí solo se pinta, y con
                    palabras además del color.
                -->
                <template #celda-empleados="{ fila }">
                    <span
                        :class="
                            fila.excedePlan
                                ? 'rounded bg-advertencia-fondo px-2 py-0.5 font-bold text-advertencia-texto'
                                : ''
                        "
                        :title="
                            fila.excedePlan
                                ? 'Supera los empleados incluidos en el plan'
                                : ''
                        "
                    >
                        {{ fila.empleados }}
                    </span>
                </template>

                <template #celda-uso="{ fila }">
                    <Etiqueta :tono="fila.usoTono">{{ fila.uso }}</Etiqueta>
                </template>

                <template #celda-pago="{ fila }">
                    <Etiqueta :tono="fila.pagoTono">{{ fila.pago }}</Etiqueta>
                </template>

                <template #celda-estado="{ fila }">
                    <Etiqueta :tono="fila.estadoTono">
                        {{ fila.estado }}
                    </Etiqueta>
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

                    <Link v-else :href="accion.url">
                        <Boton tipo="button">Crear la primera empresa</Boton>
                    </Link>
                </template>
            </TablaDatos>

            <p class="mt-3 text-apoyo text-texto-terciario">
                Cuando los empleados cargados superan los del plan, la celda se
                resalta: es una conversación de venta.
            </p>

            <Paginacion
                v-if="empresas.total > 0"
                :pagina="empresas.pagina"
                :paginas="empresas.paginas"
                :total="empresas.total"
                :desde="empresas.desde"
                :hasta="empresas.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>
    </ArmazonProveedor>
</template>
