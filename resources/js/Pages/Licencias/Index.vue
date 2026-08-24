<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import { useNovedad } from "@/Utilidades/useNovedad";
import Armazon from "@/Components/Armazon.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import ModalDeBorrado from "@/Components/ModalDeBorrado.vue";
import ModalDeNovedad from "@/Components/ModalDeNovedad.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * El tercer módulo de novedades (documento 1, 6.5 · documento 3, 5.5).
 *
 * Un solo tipo de novedad con un selector de tres opciones —licencia no
 * remunerada, ausencia y suspensión—, que se cuentan igual: días corridos,
 * domingos y festivos incluidos.
 *
 * El registro usa el flujo compartido: `ModalDeNovedad` pide el documento y
 * resuelve al empleado, y este archivo solo pone los campos propios de la
 * novedad en el `slot`. **Los días no se cuentan aquí**: los pinta
 * `diasDeLicencia`, que llega del servidor y se vuelve a pedir con una
 * recarga parcial cada vez que cambia una de las dos fechas.
 */
const props = defineProps({
    licencias: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    diasDeLicencia: { type: String, default: null },
    novedad: { type: Object, default: null },
});

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "cargo", titulo: "Cargo" },
    { clave: "tipo", titulo: "Tipo" },
    { clave: "inicio", titulo: "Inicio", ordenable: true },
    { clave: "fin", titulo: "Fin", ordenable: true },
    { clave: "dias", titulo: "Días", numerica: true },
    { clave: "acciones", titulo: "Acciones" },
];

const {
    busqueda,
    buscar,
    pedir,
    ordenar,
    quitar,
    limpiar,
    urlExportar,
    textoExportar,
} = useListado({
    ruta: "/licencias",
    filtros: () => props.filtros,
    pagina: () => props.licencias,
    tieneFiltros: () => props.tieneFiltros,
    deLista: ["tipo"],
});

const tipos = computed(() => [
    { valor: TODOS, texto: "Todos los tipos" },
    ...props.opciones.tipos,
]);

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const tipo = computed(() =>
    flujo.value.tipos.find((clase) => clase.clave === "licencia"),
);
const titulo = computed(() => tipo.value?.titulo ?? "Nueva licencia o ausencia");
const tituloEdicion = computed(
    () => tipo.value?.tituloEdicion ?? "Editar licencia o ausencia",
);

const abierto = ref(false);

const form = useForm({
    vinculacion_id: null,
    tipo: "",
    fecha_inicio: "",
    fecha_fin: "",
});

function abrirModal() {
    form.reset();
    form.clearErrors();
    abierto.value = true;
}

function cerrarModal() {
    abierto.value = false;
}

function seleccionado(empleado) {
    form.vinculacion_id = empleado.vinculacion;
}

function guardar() {
    form.post("/licencias", {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
}

/*
 * Editar y eliminar (documento 1, 6.2). Al eliminar una suspensión, la
 * acumulación de vacaciones que había reducido vuelve, y eso es lo que dice la
 * confirmación que escribe el servidor.
 */
const {
    editando,
    borrando,
    borradoAbierto,
    enviando: eliminando,
    abrirEdicion,
    abrirBorrado,
    cerrar: cerrarNovedad,
    guardarEdicion,
    confirmarBorrado,
} = useNovedad({
    ruta: "/licencias",
    form,
    novedad: () => props.novedad,
});

// Registrar y editar son el mismo modal, como en los otros dos módulos.
const modalAbierto = computed(() => abierto.value || editando.value !== null);
const tituloDelModal = computed(() =>
    editando.value ? tituloEdicion.value : titulo.value,
);

function cerrarElModal() {
    if (editando.value) {
        cerrarNovedad();
        form.reset();

        return;
    }

    cerrarModal();
}

function guardarElModal() {
    if (editando.value) {
        guardarEdicion();

        return;
    }

    guardar();
}

/*
 * Cada cambio de una de las dos fechas pide de nuevo los días calculados. Es
 * el mismo patrón que incapacidades: el servidor cuenta, el navegador solo
 * pinta.
 */
watch(
    () => [form.fecha_inicio, form.fecha_fin],
    ([fecha_inicio, fecha_fin]) => {
        if (fecha_inicio === "" || fecha_fin === "") {
            return;
        }

        router.reload({
            only: ["diasDeLicencia"],
            data: { fecha_inicio, fecha_fin },
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
);

const confirmacion = computed(() => usePage().props.estado ?? "");
const empleadoDeNovedad = computed(() => usePage().props.empleadoDeNovedad);
</script>

<template>
    <Head title="Licencias, ausencias y suspensiones" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Licencias, ausencias y suspensiones
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <a :href="urlExportar" download>
                    <Boton tipo="button" variante="secundario">
                        {{ textoExportar }}
                    </Boton>
                </a>

                <Boton tipo="button" @click="abrirModal">
                    Nueva licencia
                </Boton>
            </div>
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
                        etiqueta="Empleado"
                        tipo="search"
                        ayuda="Por nombre o documento"
                        @update:model-value="buscar"
                    />
                </div>

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

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.tipo"
                        etiqueta="Tipo"
                        :opciones="tipos"
                        :con-marcador="false"
                        @update:model-value="pedir({ tipo: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="licencias.filas"
                :clicable="false"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                :titulo-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Aún no has registrado licencias, ausencias ni suspensiones.'
                "
                :descripcion-vacio="
                    tieneFiltros
                        ? ''
                        : 'Cuando registres una, aquí verás el detalle y podrás exportarlo.'
                "
                @ordenar="ordenar"
            >
                <!--
                    Editar y eliminar, sin límite de antigüedad (documento 1,
                    6.2). El botón rojo vive dentro de la confirmación, no aquí.
                -->
                <template #celda-acciones="{ fila }">
                    <div class="flex items-center gap-1">
                        <Boton
                            tipo="button"
                            variante="texto"
                            @click="abrirEdicion(fila)"
                        >
                            Editar
                        </Boton>
                        <Boton
                            tipo="button"
                            variante="texto"
                            @click="abrirBorrado(fila)"
                        >
                            Eliminar
                        </Boton>
                    </div>
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

                    <Boton v-else tipo="button" @click="abrirModal">
                        Registrar novedad
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="licencias.total > 0"
                :pagina="licencias.pagina"
                :paginas="licencias.paginas"
                :total="licencias.total"
                :desde="licencias.desde"
                :hasta="licencias.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>

        <ModalDeNovedad
            :abierto="modalAbierto"
            :titulo="tituloDelModal"
            :flujo="flujo"
            :empleado="empleadoDeNovedad"
            :edicion="editando"
            :enviando="form.processing"
            :deshabilitado="!diasDeLicencia || !form.tipo"
            @cerrar="cerrarElModal"
            @seleccionado="seleccionado"
            @guardar="guardarElModal"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Selector
                    v-model="form.tipo"
                    etiqueta="Tipo de novedad"
                    :opciones="opciones.tipos"
                    :error="form.errors.tipo"
                />
                <CampoFecha
                    v-model="form.fecha_inicio"
                    etiqueta="Fecha inicial"
                    :error="form.errors.fecha_inicio"
                />
                <CampoFecha
                    v-model="form.fecha_fin"
                    etiqueta="Fecha final"
                    :error="form.errors.fecha_fin"
                />
                <CampoTexto
                    :model-value="diasDeLicencia ?? ''"
                    etiqueta="Días"
                    solo-lectura
                    ayuda="Días corridos, domingos y festivos incluidos"
                />
            </div>
        </ModalDeNovedad>

        <!-- La confirmación con la consecuencia calculada (documento 3, 6.3). -->
        <ModalDeBorrado
            :abierto="borradoAbierto"
            titulo="Eliminar novedad"
            :mensaje="borrando?.confirmacion ?? ''"
            :enviando="eliminando"
            @cerrar="cerrarNovedad"
            @confirmar="confirmarBorrado"
        />
    </Armazon>
</template>
