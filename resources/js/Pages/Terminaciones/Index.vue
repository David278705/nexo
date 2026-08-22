<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ModalDeNovedad from "@/Components/ModalDeNovedad.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * Terminaciones (documento 1, capítulo 7 · documento 3, 5.6).
 *
 * Comparte el flujo de registro con los otros cuatro módulos:
 * `ModalDeNovedad` pide el documento y resuelve al empleado, y este archivo
 * solo pone en el `slot` los campos propios de la salida.
 *
 * Lo propio de esta pantalla son tres cosas:
 *
 * 1. **El motivo es una lista dependiente**: al cambiar el tipo de salida se
 *    recargan sus opciones, que llegan del servidor en `motivosPorTipo`.
 * 2. **En expiración del plazo y en obra terminada el motivo no se elige**: lo
 *    autocompleta el tipo y el campo queda deshabilitado. Quien decide de
 *    verdad es el servidor (`TipoDeSalida::motivoResultante()`); aquí solo se
 *    refleja.
 * 3. **Antes de guardar se dice la consecuencia**, con el nombre del empleado.
 *    La frase la escribe el servidor y llega en `avisoDeTerminacion`.
 *
 * La categoría de rotación de la tabla tampoco se calcula aquí: llega en la
 * fila, con su tono, deducida del tipo de salida (documento 1, 7.4).
 */
const props = defineProps({
    terminaciones: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    avisoDeTerminacion: { type: String, default: null },
});

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "cargo", titulo: "Cargo" },
    { clave: "salida", titulo: "Fecha de salida", ordenable: true },
    { clave: "tipo", titulo: "Tipo de salida" },
    { clave: "motivo", titulo: "Motivo" },
    { clave: "rotacion", titulo: "Rotación" },
];

const { pedir, ordenar, quitar, limpiar, urlExportar, textoExportar } =
    useListado({
        ruta: "/terminaciones",
        filtros: () => props.filtros,
        pagina: () => props.terminaciones,
        tieneFiltros: () => props.tieneFiltros,
        deLista: ["tipo", "motivo"],
    });

const tipos = computed(() => [
    { valor: TODOS, texto: "Todos los tipos de salida" },
    ...props.opciones.tipos,
]);

const motivosDelFiltro = computed(() => [
    { valor: TODOS, texto: "Todos los motivos" },
    ...props.opciones.motivos.map((motivo) => ({
        valor: motivo,
        texto: motivo,
    })),
]);

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const titulo = computed(
    () =>
        flujo.value.tipos.find((tipo) => tipo.clave === "terminacion")?.titulo ??
        "Registrar terminación",
);

const abierto = ref(false);

const form = useForm({
    vinculacion_id: null,
    tipo_salida: "",
    motivo: "",
    fecha_salida: "",
    observaciones: "",
});

// La lista dependiente del documento 1, 7.3: lo que ofrece el campo de motivo
// depende del tipo elegido, y sin tipo todavía no ofrece nada.
const dependencia = computed(
    () => props.opciones.motivosPorTipo[form.tipo_salida] ?? null,
);

const motivos = computed(() =>
    (dependencia.value?.motivos ?? []).map((motivo) => ({
        valor: motivo,
        texto: motivo,
    })),
);

const motivoAutomatico = computed(() => dependencia.value?.automatico ?? null);

const ayudaDelMotivo = computed(() =>
    motivoAutomatico.value
        ? "Este tipo de salida tiene un solo motivo. El sistema lo completa solo."
        : "Las opciones cambian según el tipo de salida",
);

const puedeGuardar = computed(
    () =>
        form.tipo_salida !== "" &&
        form.motivo !== "" &&
        form.fecha_salida !== "",
);

/*
 * Al cambiar el tipo de salida, el motivo anterior deja de tener sentido: los
 * de un tipo no son los de otro. Si el tipo trae motivo automático se rellena
 * solo; si no, el campo vuelve a estar sin elegir.
 */
watch(
    () => form.tipo_salida,
    () => {
        form.motivo = motivoAutomatico.value ?? "";
    },
);

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
    form.post("/terminaciones", {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
}

/*
 * En cuanto el modal resuelve al empleado se pide la frase de la confirmación,
 * que lleva su nombre. Mismo patrón de recarga parcial que la situación actual
 * del salario en movimientos: el texto lo escribe el servidor.
 */
watch(
    () => form.vinculacion_id,
    (vinculacion_id) => {
        if (!vinculacion_id) {
            return;
        }

        router.reload({
            only: ["avisoDeTerminacion"],
            data: { vinculacion_id },
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
    <Head title="Terminaciones" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Terminaciones
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <a :href="urlExportar" download>
                    <Boton tipo="button" variante="secundario">
                        {{ textoExportar }}
                    </Boton>
                </a>

                <Boton tipo="button" @click="abrirModal">
                    Registrar terminación
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

                <div class="min-w-[240px]">
                    <Selector
                        :model-value="filtros.tipo"
                        etiqueta="Tipo de salida"
                        :opciones="tipos"
                        :con-marcador="false"
                        @update:model-value="pedir({ tipo: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[240px]">
                    <Selector
                        :model-value="filtros.motivo"
                        etiqueta="Motivo de la baja"
                        :opciones="motivosDelFiltro"
                        :con-marcador="false"
                        @update:model-value="pedir({ motivo: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="terminaciones.filas"
                :clicable="false"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Aún no has registrado salidas."
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Cuando registres una, aquí verás el tipo de salida, el motivo y la categoría de rotación.'
                "
                @ordenar="ordenar"
            >
                <template #celda-rotacion="{ fila }">
                    <Etiqueta :tono="fila.tono">{{ fila.rotacion }}</Etiqueta>
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
                        Registrar terminación
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="terminaciones.total > 0"
                :pagina="terminaciones.pagina"
                :paginas="terminaciones.paginas"
                :total="terminaciones.total"
                :desde="terminaciones.desde"
                :hasta="terminaciones.hasta"
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
            :deshabilitado="!puedeGuardar"
            @cerrar="cerrarModal"
            @seleccionado="seleccionado"
            @guardar="guardar"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <CampoFecha
                    v-model="form.fecha_salida"
                    etiqueta="Fecha de salida"
                    :error="form.errors.fecha_salida"
                />
                <Selector
                    v-model="form.tipo_salida"
                    etiqueta="Tipo de salida"
                    :opciones="opciones.tipos"
                    :error="form.errors.tipo_salida"
                />
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <Selector
                    v-model="form.motivo"
                    etiqueta="Motivo de la baja"
                    :opciones="motivos"
                    :deshabilitado="motivoAutomatico !== null"
                    :ayuda="ayudaDelMotivo"
                    :error="form.errors.motivo"
                />
                <CampoTexto
                    v-model="form.observaciones"
                    etiqueta="Observaciones"
                    opcional
                    :error="form.errors.observaciones"
                />
            </div>

            <Aviso v-if="avisoDeTerminacion" tono="advertencia" class="mt-4">
                {{ avisoDeTerminacion }}
            </Aviso>
        </ModalDeNovedad>
    </Armazon>
</template>
