<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { useListado } from "@/Utilidades/useListado";
import { useNovedad } from "@/Utilidades/useNovedad";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
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
 * El segundo módulo de novedades (documento 1, 6.4 · documento 3, 5.5), y el
 * primero que descuenta del saldo.
 *
 * El registro usa el flujo compartido: `ModalDeNovedad` pide el documento y
 * resuelve al empleado, y este archivo solo pone los campos propios del
 * periodo de vacaciones en el `slot`.
 *
 * **Los días no se cuentan aquí.** Los pinta `diasDeVacaciones`, que llega
 * del servidor y se vuelve a pedir con una recarga parcial cada vez que
 * cambia una de las dos fechas (igual que incapacidades y que el modal de
 * renovación de contrato). **Tampoco el saldo ni la advertencia de F2-06**:
 * `saldoDeVacaciones` llega igual, escrito por `App\Vacaciones\AdvertenciaDeSaldo`,
 * con el saldo de hoy, el proyectado a la fecha de disfrute y el texto ámbar
 * exacto del documento 3 cuando los días solicitados no alcanzan. No bloquea.
 */
const props = defineProps({
    vacaciones: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    diasDeVacaciones: { type: String, default: null },
    saldoDeVacaciones: { type: Object, default: null },
    novedad: { type: Object, default: null },
});

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "cargo", titulo: "Cargo" },
    { clave: "inicio", titulo: "Inicio", ordenable: true },
    { clave: "fin", titulo: "Fin", ordenable: true },
    { clave: "dias", titulo: "Días hábiles", numerica: true },
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
    ruta: "/vacaciones",
    filtros: () => props.filtros,
    pagina: () => props.vacaciones,
    tieneFiltros: () => props.tieneFiltros,
    deLista: [],
});

const cargos = computed(() => [
    { valor: "", texto: "Todos los cargos" },
    ...props.opciones.cargos.map((cargo) => ({ valor: cargo, texto: cargo })),
]);

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const tipo = computed(() =>
    flujo.value.tipos.find((clase) => clase.clave === "vacaciones"),
);
const titulo = computed(() => tipo.value?.titulo ?? "Registrar vacaciones");
const tituloEdicion = computed(
    () => tipo.value?.tituloEdicion ?? "Editar vacaciones",
);

const abierto = ref(false);

const form = useForm({
    vinculacion_id: null,
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
    form.post("/vacaciones", {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
}

/*
 * Editar y eliminar (documento 1, 6.2). El mismo formulario y el mismo modal
 * del registro, con los valores puestos; la consecuencia del borrado —cuántos
 * días se devuelven y cómo queda el saldo— la escribe el servidor.
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
    ruta: "/vacaciones",
    form,
    novedad: () => props.novedad,
});

/*
 * Cada cambio del empleado seleccionado o de una de las dos fechas pide de
 * nuevo los días hábiles y la franja de saldo. Es el mismo patrón que
 * incapacidades: el servidor cuenta, el navegador solo pinta. El saldo de hoy
 * ya se puede pedir con solo el empleado resuelto, antes de que existan fechas.
 */
watch(
    () => [form.vinculacion_id, form.fecha_inicio, form.fecha_fin],
    ([vinculacion_id, fecha_inicio, fecha_fin]) => {
        if (!vinculacion_id) {
            return;
        }

        router.reload({
            only: ["diasDeVacaciones", "saldoDeVacaciones"],
            data: { vinculacion_id, fecha_inicio, fecha_fin },
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
);

/*
 * Registrar y editar son el mismo modal —el usuario no está aprendiendo una
 * pantalla nueva—, así que la pantalla monta uno solo y le dice cuál de las dos
 * cosas está haciendo.
 */
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

const confirmacion = computed(() => usePage().props.estado ?? "");
const empleadoDeNovedad = computed(() => usePage().props.empleadoDeNovedad);
</script>

<template>
    <Head title="Vacaciones" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Vacaciones
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <a :href="urlExportar" download>
                    <Boton tipo="button" variante="secundario">
                        {{ textoExportar }}
                    </Boton>
                </a>

                <Boton tipo="button" @click="abrirModal">
                    Registrar vacaciones
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
                        :model-value="filtros.cargo"
                        etiqueta="Cargo"
                        :opciones="cargos"
                        marcador="Todos los cargos"
                        @update:model-value="pedir({ cargo: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="vacaciones.filas"
                :clicable="false"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Aún no has registrado vacaciones."
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Cuando registres unas, aquí verás el detalle y podrás exportarlo.'
                "
                @ordenar="ordenar"
            >
                <!--
                    Editar y eliminar, sin límite de antigüedad (documento 1,
                    6.2). Ninguno de los dos es un botón rojo suelto en la
                    tabla: el destructivo vive dentro de la confirmación.
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
                        Registrar vacaciones
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="vacaciones.total > 0"
                :pagina="vacaciones.pagina"
                :paginas="vacaciones.paginas"
                :total="vacaciones.total"
                :desde="vacaciones.desde"
                :hasta="vacaciones.hasta"
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
            :deshabilitado="!diasDeVacaciones"
            @cerrar="cerrarElModal"
            @seleccionado="seleccionado"
            @guardar="guardarElModal"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <CampoFecha
                    v-model="form.fecha_inicio"
                    etiqueta="Día inicial de vacaciones"
                    :error="form.errors.fecha_inicio"
                />
                <CampoFecha
                    v-model="form.fecha_fin"
                    etiqueta="Día final de vacaciones"
                    :error="form.errors.fecha_fin"
                />
                <CampoTexto
                    :model-value="diasDeVacaciones ?? ''"
                    etiqueta="Días de vacaciones"
                    solo-lectura
                    ayuda="Días hábiles: domingos y festivos no cuentan"
                />
            </div>

            <!--
                La franja permanente del documento 3, 5.5: el saldo de hoy y el
                proyectado a la fecha de disfrute, con la advertencia ámbar de
                F2-06 cuando los días solicitados no alcanzan. No bloquea.
            -->
            <div v-if="saldoDeVacaciones" class="mt-4 space-y-3">
                <div class="grid gap-4 sm:grid-cols-2">
                    <CampoTexto
                        :model-value="saldoDeVacaciones.saldoActual"
                        etiqueta="Saldo actual"
                        solo-lectura
                    />
                    <CampoTexto
                        :model-value="saldoDeVacaciones.saldoProyectado ?? '—'"
                        etiqueta="Saldo proyectado a la fecha de disfrute"
                        solo-lectura
                    />
                </div>

                <Aviso v-if="saldoDeVacaciones.advertencia" tono="advertencia">
                    {{ saldoDeVacaciones.advertencia }}
                </Aviso>
            </div>
        </ModalDeNovedad>

        <!--
            La confirmación del documento 3, 2.4: «Se eliminarán 8 días de
            vacaciones y el saldo de Carlos Ramírez pasará de 4,25 a 12,25
            días». La frase entera llega del servidor.
        -->
        <ModalDeBorrado
            :abierto="borradoAbierto"
            titulo="Eliminar vacaciones"
            :mensaje="borrando?.confirmacion ?? ''"
            :enviando="eliminando"
            @cerrar="cerrarNovedad"
            @confirmar="confirmarBorrado"
        />
    </Armazon>
</template>
