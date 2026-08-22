<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { useListado } from "@/Utilidades/useListado";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import ModalDeNovedad from "@/Components/ModalDeNovedad.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Paginacion from "@/Components/Paginacion.vue";
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
});

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "cargo", titulo: "Cargo" },
    { clave: "inicio", titulo: "Inicio", ordenable: true },
    { clave: "fin", titulo: "Fin", ordenable: true },
    { clave: "dias", titulo: "Días hábiles", numerica: true },
];

const { pedir, ordenar, quitar, limpiar, urlExportar, textoExportar } =
    useListado({
        ruta: "/vacaciones",
        filtros: () => props.filtros,
        pagina: () => props.vacaciones,
        tieneFiltros: () => props.tieneFiltros,
        deLista: [],
    });

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const titulo = computed(
    () =>
        flujo.value.tipos.find((tipo) => tipo.clave === "vacaciones")
            ?.titulo ?? "Registrar vacaciones",
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
            :abierto="abierto"
            :titulo="titulo"
            :flujo="flujo"
            :empleado="empleadoDeNovedad"
            :enviando="form.processing"
            :deshabilitado="!diasDeVacaciones"
            @cerrar="cerrarModal"
            @seleccionado="seleccionado"
            @guardar="guardar"
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
    </Armazon>
</template>
