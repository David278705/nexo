<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import Armazon from "@/Components/Armazon.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import ModalDeNovedad from "@/Components/ModalDeNovedad.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * El primer módulo de novedades (documento 1, 6.3 · documento 3, 5.5).
 *
 * El registro usa el flujo compartido: `ModalDeNovedad` pide el documento y
 * resuelve al empleado, y este archivo solo pone los campos propios de la
 * incapacidad en el `slot`.
 *
 * **Los días no se cuentan aquí.** Los pinta `diasDeIncapacidad`, que llega
 * del servidor y se vuelve a pedir con una recarga parcial cada vez que
 * cambia una de las dos fechas (igual que el modal de renovación de
 * contrato).
 *
 * El código de diagnóstico no se recorta en ningún caso: quien ve esta
 * pantalla ya pasó el middleware `modulo:incapacidades`, que es el permiso
 * que la sección 6.3 exige para verlo.
 */
const props = defineProps({
    incapacidades: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    diasDeIncapacidad: { type: String, default: null },
});

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "cargo", titulo: "Cargo" },
    { clave: "inicio", titulo: "Inicio", ordenable: true },
    { clave: "fin", titulo: "Fin", ordenable: true },
    { clave: "dias", titulo: "Días", numerica: true },
    { clave: "diagnostico", titulo: "Diagnóstico" },
    { clave: "origen", titulo: "Origen" },
    { clave: "prorroga", titulo: "Prórroga" },
];

const { pedir, ordenar, quitar, limpiar, urlExportar, textoExportar } =
    useListado({
        ruta: "/incapacidades",
        filtros: () => props.filtros,
        pagina: () => props.incapacidades,
        tieneFiltros: () => props.tieneFiltros,
        deLista: ["origen", "eps"],
    });

const origenes = computed(() => [
    { valor: TODOS, texto: "Todos los orígenes" },
    ...props.opciones.origenes,
]);

const epsDisponibles = computed(() => [
    { valor: TODOS, texto: "Todas las EPS" },
    ...props.opciones.eps,
]);

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const titulo = computed(
    () =>
        flujo.value.tipos.find((tipo) => tipo.clave === "incapacidad")
            ?.titulo ?? "Nueva incapacidad",
);

const abierto = ref(false);

const form = useForm({
    vinculacion_id: null,
    fecha_inicio: "",
    fecha_fin: "",
    codigo_diagnostico: "",
    es_prorroga: "no",
    eps_id: "",
    origen: "",
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
    form.post("/incapacidades", {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
}

/*
 * Cada cambio de una de las dos fechas pide de nuevo los días calculados. Es
 * el mismo patrón que el modal de renovación de contrato: el servidor cuenta,
 * el navegador solo pinta.
 */
watch(
    () => [form.fecha_inicio, form.fecha_fin],
    ([fecha_inicio, fecha_fin]) => {
        if (fecha_inicio === "" || fecha_fin === "") {
            return;
        }

        router.reload({
            only: ["diasDeIncapacidad"],
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
    <Head title="Incapacidades" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Incapacidades
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <a :href="urlExportar" download>
                    <Boton tipo="button" variante="secundario">
                        {{ textoExportar }}
                    </Boton>
                </a>

                <Boton tipo="button" @click="abrirModal">
                    Nueva incapacidad
                </Boton>
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

                <div class="min-w-[170px]">
                    <Selector
                        :model-value="filtros.origen"
                        etiqueta="Origen"
                        :opciones="origenes"
                        :con-marcador="false"
                        @update:model-value="
                            pedir({ origen: $event, page: 1 })
                        "
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.eps"
                        etiqueta="EPS"
                        :opciones="epsDisponibles"
                        :con-marcador="false"
                        @update:model-value="pedir({ eps: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="incapacidades.filas"
                :clicable="false"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Aún no has registrado incapacidades."
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Cuando registres una, aquí verás el detalle y podrás exportarlo.'
                "
                @ordenar="ordenar"
            >
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
                        Registrar incapacidad
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="incapacidades.total > 0"
                :pagina="incapacidades.pagina"
                :paginas="incapacidades.paginas"
                :total="incapacidades.total"
                :desde="incapacidades.desde"
                :hasta="incapacidades.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>

        <ModalDeNovedad
            :abierto="abierto"
            :titulo="titulo"
            :flujo="flujo"
            :empleado="empleadoDeNovedad"
            :enviando="form.processing"
            :deshabilitado="!diasDeIncapacidad"
            @cerrar="cerrarModal"
            @seleccionado="seleccionado"
            @guardar="guardar"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <CampoFecha
                    v-model="form.fecha_inicio"
                    etiqueta="Fecha de inicio"
                    :error="form.errors.fecha_inicio"
                />
                <CampoFecha
                    v-model="form.fecha_fin"
                    etiqueta="Fecha de terminación"
                    :error="form.errors.fecha_fin"
                />
                <CampoTexto
                    :model-value="diasDeIncapacidad ?? ''"
                    etiqueta="Días de incapacidad"
                    solo-lectura
                    ayuda="Días corridos, incluyendo el inicial y el final"
                />
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CampoTexto
                    v-model="form.codigo_diagnostico"
                    etiqueta="Código de diagnóstico"
                    :error="form.errors.codigo_diagnostico"
                    ayuda="Dato sensible · acceso restringido"
                />
                <Selector
                    v-model="form.es_prorroga"
                    etiqueta="¿Es prórroga?"
                    :opciones="[
                        { valor: 'no', texto: 'No' },
                        { valor: 'si', texto: 'Sí' },
                    ]"
                    :con-marcador="false"
                    :error="form.errors.es_prorroga"
                />
                <Selector
                    v-model="form.eps_id"
                    etiqueta="EPS que la expide"
                    :opciones="opciones.eps"
                    :error="form.errors.eps_id"
                />
                <Selector
                    v-model="form.origen"
                    etiqueta="Origen"
                    :opciones="opciones.origenes"
                    :error="form.errors.origen"
                />
            </div>
        </ModalDeNovedad>
    </Armazon>
</template>
