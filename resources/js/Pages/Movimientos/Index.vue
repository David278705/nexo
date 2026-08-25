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
 * 3. **Solo el movimiento más reciente de cada empleado se corrige o se
 *    elimina** (REV-03). Las demás filas no ofrecen las acciones y dicen por
 *    qué: eliminar devuelve el cargo y el salario anteriores, y con otro
 *    movimiento encima ya no hay un «antes» claro al que volver. Quien lo
 *    decide es el servidor, en la prop `reversible` de cada fila.
 */
const props = defineProps({
    movimientos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    situacionActual: { type: Object, default: null },
    novedad: { type: Object, default: null },
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
    { clave: "acciones", titulo: "Acciones" },
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

const cargos = computed(() => [
    { valor: "", texto: "Todos los cargos" },
    ...props.opciones.cargos.map((cargo) => ({ valor: cargo, texto: cargo })),
]);

// El título de los cinco módulos lo decide `TipoDeNovedad` en el servidor, y
// llega en la prop compartida `flujoDeNovedad`.
const flujo = computed(() => usePage().props.flujoDeNovedad);
const clase = computed(() =>
    flujo.value.tipos.find((tipo) => tipo.clave === "movimiento"),
);
const titulo = computed(
    () => clase.value?.titulo ?? "Nuevo movimiento salarial",
);
const tituloEdicion = computed(
    () => clase.value?.tituloEdicion ?? "Editar movimiento salarial",
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

/*
 * Editar y eliminar (REV-03). El mismo formulario y el mismo modal del
 * registro, con los valores puestos; la consecuencia del borrado —a qué cargo y
 * a qué salario vuelve el empleado— la escribe el servidor.
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
    ruta: "/movimientos",
    form,
    novedad: () => props.novedad,
});

/*
 * El punto de partida del movimiento. Al registrar es la situación vigente del
 * empleado, que llega en `situacionActual`; al corregir son el cargo y el
 * salario que esta misma fila guardó como anteriores, porque el empleado ya
 * lleva encima los nuevos y preguntárselo a él enseñaría el resultado en la
 * casilla del punto de partida. Los dos los escribe el servidor.
 */
const cargoActual = computed(
    () => editando.value?.contexto.cargo ?? props.situacionActual?.cargo ?? "",
);
const salarioActual = computed(
    () =>
        editando.value?.contexto.salario ?? props.situacionActual?.salario ?? "",
);

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
        // Al corregir no se pregunta: el punto de partida es el que guardó el
        // propio movimiento, y el del empleado sería el resultado de este.
        if (!vinculacion_id || editando.value) {
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

/*
 * Registrar y corregir son el mismo modal —el usuario no está aprendiendo una
 * pantalla nueva—, así que la pantalla monta uno solo y le dice cuál de las dos
 * cosas está haciendo. Es lo mismo que hacen las tres novedades del capítulo 6.
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
                :filas="movimientos.filas"
                :clicable="false"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                :titulo-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Aún no has registrado aumentos ni promociones.'
                "
                :descripcion-vacio="
                    tieneFiltros
                        ? ''
                        : 'Cuando registres uno, aquí verás el cargo y el salario anteriores frente a los nuevos.'
                "
                @ordenar="ordenar"
            >
                <!--
                    Solo el movimiento más reciente del empleado se corrige o se
                    elimina (REV-03). En las demás filas no se ofrecen las
                    acciones y se dice por qué, en vez de ofrecerlas para
                    retirarlas después con un error.
                -->
                <template #celda-acciones="{ fila }">
                    <div v-if="fila.reversible" class="flex items-center gap-1">
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

                    <span v-else class="text-tabla text-texto-secundario">
                        Hay uno posterior
                    </span>
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
            :abierto="modalAbierto"
            :titulo="tituloDelModal"
            :flujo="flujo"
            :empleado="empleadoDeNovedad"
            :edicion="editando"
            :enviando="form.processing"
            :deshabilitado="!puedeGuardar"
            @cerrar="cerrarElModal"
            @seleccionado="seleccionado"
            @guardar="guardarElModal"
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

        <!--
            La confirmación del documento 3, 2.4, con la consecuencia calculada:
            «El salario de Carolina Restrepo Vélez volverá a $ 2.700.000». La
            frase entera llega del servidor.
        -->
        <ModalDeBorrado
            :abierto="borradoAbierto"
            titulo="Eliminar movimiento"
            :mensaje="borrando?.confirmacion ?? ''"
            :enviando="eliminando"
            @cerrar="cerrarNovedad"
            @confirmar="confirmarBorrado"
        />
    </Armazon>
</template>
