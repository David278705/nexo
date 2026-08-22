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
 * Aumentos y promociones (documento 1, 8.1 · documento 3, 5.6).
 *
 * Comparte el flujo de registro con las tres novedades del capítulo 6:
 * `ModalDeNovedad` pide el documento y resuelve al empleado, y este archivo
 * solo pone en el `slot` los campos propios del movimiento.
 *
 * Lo propio de esta pantalla son dos cosas:
 *
 * 1. **El cargo y el salario actuales son de solo lectura y llegan del
 *    servidor** en `situacionActual`, que se pide con una recarga parcial en
 *    cuanto el modal resuelve al empleado.
 * 2. **En un aumento salarial el cargo no se digita**: se repite el actual y el
 *    campo queda de solo lectura. Quien decide de verdad es el servidor
 *    (`TipoDeMovimiento::cargoResultante()`); aquí solo se refleja.
 */
const props = defineProps({
    movimientos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    situacionActual: { type: Object, default: null },
});

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "tipo", titulo: "Tipo" },
    { clave: "cargoAnterior", titulo: "Cargo anterior" },
    { clave: "cargoNuevo", titulo: "Cargo nuevo" },
    { clave: "salarioAnterior", titulo: "Salario anterior", numerica: true },
    { clave: "salarioNuevo", titulo: "Salario nuevo", numerica: true },
    { clave: "efectivo", titulo: "Efectivo desde", ordenable: true },
];

const { pedir, ordenar, quitar, limpiar, urlExportar, textoExportar } =
    useListado({
        ruta: "/movimientos",
        filtros: () => props.filtros,
        pagina: () => props.movimientos,
        tieneFiltros: () => props.tieneFiltros,
        deLista: ["tipo"],
    });

const tipos = computed(() => [
    { valor: TODOS, texto: "Todos los movimientos" },
    ...props.opciones.tipos,
]);

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const titulo = computed(
    () =>
        flujo.value.tipos.find((tipo) => tipo.clave === "movimiento")?.titulo ??
        "Nuevo movimiento salarial",
);

const abierto = ref(false);

const form = useForm({
    vinculacion_id: null,
    tipo: "",
    cargo_nuevo: "",
    salario_nuevo: "",
    fecha_efectividad: "",
});

const esPromocion = computed(() => form.tipo === "promocion");

const cargoActual = computed(() => props.situacionActual?.cargo ?? "");
const salarioActual = computed(() => props.situacionActual?.salario ?? "");

/*
 * En un aumento salarial el campo enseña el cargo actual y no lo que haya en el
 * formulario: «se repite el mismo cargo» (documento 1, 8.1). El que manda sigue
 * siendo el servidor —`TipoDeMovimiento::cargoResultante()`—, así que aquí no se
 * escribe nada en `form.cargo_nuevo` que luego haya que acordarse de limpiar.
 */
const cargoNuevo = computed(() =>
    esPromocion.value ? form.cargo_nuevo : cargoActual.value,
);

const puedeGuardar = computed(
    () =>
        form.tipo !== "" &&
        form.salario_nuevo !== "" &&
        form.fecha_efectividad !== "" &&
        (!esPromocion.value || form.cargo_nuevo.trim() !== ""),
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
    form.post("/movimientos", {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
}

/*
 * En cuanto el modal resuelve al empleado se piden su cargo y su salario
 * vigentes. Es el mismo patrón de recarga parcial que usan los días contados de
 * las otras novedades: el servidor es el que sabe, y el navegador solo pinta.
 */
watch(
    () => form.vinculacion_id,
    (vinculacion_id) => {
        if (!vinculacion_id) {
            return;
        }

        router.reload({
            only: ["situacionActual"],
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
    <Head title="Aumentos y promociones" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Aumentos y promociones
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <a :href="urlExportar" download>
                    <Boton tipo="button" variante="secundario">
                        {{ textoExportar }}
                    </Boton>
                </a>

                <Boton tipo="button" @click="abrirModal">
                    Nuevo movimiento
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
                :filas="movimientos.filas"
                :clicable="false"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Aún no has registrado aumentos ni promociones."
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Cuando registres uno, aquí verás el cargo y el salario anteriores frente a los nuevos.'
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
                        Registrar movimiento
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="movimientos.total > 0"
                :pagina="movimientos.pagina"
                :paginas="movimientos.paginas"
                :total="movimientos.total"
                :desde="movimientos.desde"
                :hasta="movimientos.hasta"
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
                <Selector
                    v-model="form.tipo"
                    etiqueta="Tipo de movimiento"
                    :opciones="opciones.tiposDelFormulario"
                    :error="form.errors.tipo"
                />
                <CampoFecha
                    v-model="form.fecha_efectividad"
                    etiqueta="Fecha de efectividad"
                    :error="form.errors.fecha_efectividad"
                />
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CampoTexto
                    :model-value="cargoActual"
                    etiqueta="Cargo actual"
                    solo-lectura
                />
                <CampoTexto
                    :model-value="cargoNuevo"
                    etiqueta="Nuevo cargo"
                    :solo-lectura="!esPromocion"
                    @update:model-value="form.cargo_nuevo = $event"
                    :ayuda="
                        esPromocion
                            ? ''
                            : 'Un aumento salarial mantiene el mismo cargo.'
                    "
                    :error="form.errors.cargo_nuevo"
                />
                <CampoTexto
                    :model-value="salarioActual"
                    etiqueta="Salario actual"
                    solo-lectura
                />
                <CampoTexto
                    v-model="form.salario_nuevo"
                    etiqueta="Nuevo salario"
                    tipo="number"
                    :error="form.errors.salario_nuevo"
                />
            </div>
        </ModalDeNovedad>
    </Armazon>
</template>
