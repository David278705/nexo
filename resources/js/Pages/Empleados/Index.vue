<script setup>
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import Armazon from "@/Components/Armazon.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * El listado de empleados (documento 3, sección 5.3).
 *
 * Una fila es una vinculación, no una persona: quien entró dos veces a la
 * empresa aparece dos veces, con su cargo y su fecha de ingreso de cada vez.
 *
 * Nada se filtra aquí. Cada cambio vuelve a pedir la página al servidor con los
 * filtros en la dirección, que es lo que hace que al volver de la ficha todo
 * siga donde estaba y que el enlace se pueda copiar y compartir.
 */
const props = defineProps({
    empleados: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    conteos: { type: Object, required: true },
});

const TODOS = "todos";

const columnas = [
    { clave: "documento", titulo: "Documento", ordenable: true },
    { clave: "nombre", titulo: "Nombre", ordenable: true },
    { clave: "cargo", titulo: "Cargo", ordenable: true },
    { clave: "contrato", titulo: "Tipo de contrato", ordenable: true },
    { clave: "ingreso", titulo: "Fecha de ingreso", ordenable: true },
    { clave: "estado", titulo: "Estado", ordenable: true },
];

// La caja de búsqueda escribe sola; el resto de controles disparan al cambiar.
const busqueda = ref(props.filtros.buscar);

watch(
    () => props.filtros.buscar,
    (valor) => {
        busqueda.value = valor;
    },
);

let temporizador = null;

function buscar(valor) {
    clearTimeout(temporizador);
    // Medio segundo de espera: pedir la página en cada tecla castiga al
    // servidor y hace parpadear la tabla mientras se escribe un apellido.
    temporizador = setTimeout(() => pedir({ buscar: valor, page: 1 }), 400);
}

/*
 * Vuelve a pedir el listado con los filtros cambiados. `preserveState` mantiene
 * el foco y lo escrito; `replace` evita que cada tecla deje una entrada en el
 * historial del navegador.
 */
function pedir(cambios) {
    router.get(
        "/empleados",
        { ...props.filtros, ...cambios },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function ordenar(clave) {
    const mismaColumna = props.filtros.orden === clave;
    const direccion =
        mismaColumna && props.filtros.direccion === "asc" ? "desc" : "asc";

    pedir({ orden: clave, direccion, page: 1 });
}

// Quitar un filtro lo devuelve a su valor de reposo: los de lista, a «todos»;
// los de texto y fecha, a vacío.
function quitar(clave) {
    pedir({ [clave]: clave === "estado" || clave === "contrato" ? TODOS : "", page: 1 });
}

function limpiar() {
    router.get("/empleados", {}, { preserveScroll: true, replace: true });
}

function abrir(fila) {
    router.visit(`/empleados/${fila.id}`);
}

const estados = computed(() => [
    { valor: TODOS, texto: "Todos los estados" },
    ...props.opciones.estados,
]);

const contratos = computed(() => [
    { valor: TODOS, texto: "Todos los contratos" },
    ...props.opciones.contratos,
]);

const cargos = computed(() => [
    { valor: "", texto: "Todos los cargos" },
    ...props.opciones.cargos.map((cargo) => ({ valor: cargo, texto: cargo })),
]);

// La dirección de la exportación lleva los mismos filtros que la pantalla, para
// que el archivo traiga exactamente lo que se está viendo.
const urlExportar = computed(() => {
    const parametros = new URLSearchParams();

    Object.entries(props.filtros).forEach(([clave, valor]) => {
        if (valor !== "" && valor !== null && clave !== "porPagina") {
            parametros.append(clave, valor);
        }
    });

    return `/empleados/exportar?${parametros.toString()}`;
});

// «Exportar 43 registros filtrados» (documento 3, sección 6.1): el botón dice
// cuántos se lleva y que son los de los filtros puestos, no todos.
const textoExportar = computed(() => {
    const uno = props.empleados.total === 1;
    const registros = uno ? "registro" : "registros";
    const filtrados = props.tieneFiltros ? (uno ? " filtrado" : " filtrados") : "";

    return `Exportar ${props.empleados.total} ${registros}${filtrados}`;
});

/*
 * La confirmación de lo que se acaba de guardar en otra pantalla. Quien termina
 * el registro de un empleado aterriza aquí, y sin esto la única señal de que
 * salió bien sería encontrar la fila a ojo.
 *
 * Se lee de la sesión, que es donde la deja el controlador, y no de una prop
 * propia: así cualquier acción que redirija al listado la aprovecha.
 */
const confirmacion = computed(() => usePage().props.estado ?? "");
</script>

<template>
    <Head title="Empleados" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">Empleados</h1>
                <p class="mt-1 text-apoyo text-texto-secundario">
                    {{ conteos.activos }} activos · {{ conteos.inactivos }}
                    inactivos
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a :href="urlExportar" download>
                    <Boton tipo="button" variante="secundario">
                        {{ textoExportar }}
                    </Boton>
                </a>

                <Link href="/empleados/nuevo">
                    <Boton tipo="button">Nuevo empleado</Boton>
                </Link>
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
                        etiqueta="Buscar por nombre o documento"
                        tipo="search"
                        @update:model-value="buscar"
                    />
                </div>

                <div class="min-w-[170px]">
                    <Selector
                        :model-value="filtros.estado"
                        etiqueta="Estado"
                        :opciones="estados"
                        marcador="Todos los estados"
                        @update:model-value="pedir({ estado: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.contrato"
                        etiqueta="Tipo de contrato"
                        :opciones="contratos"
                        marcador="Todos los contratos"
                        @update:model-value="
                            pedir({ contrato: $event, page: 1 })
                        "
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

                <div class="min-w-[160px]">
                    <CampoFecha
                        :model-value="filtros.ingresoDesde"
                        etiqueta="Ingreso desde"
                        opcional
                        @update:model-value="
                            pedir({ ingresoDesde: $event, page: 1 })
                        "
                    />
                </div>

                <div class="min-w-[160px]">
                    <CampoFecha
                        :model-value="filtros.ingresoHasta"
                        etiqueta="Ingreso hasta"
                        opcional
                        @update:model-value="
                            pedir({ ingresoHasta: $event, page: 1 })
                        "
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="empleados.filas"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                :titulo-vacio="
                    tieneFiltros
                        ? 'No encontramos resultados con estos filtros.'
                        : 'Aún no hay empleados registrados.'
                "
                :descripcion-vacio="
                    tieneFiltros
                        ? ''
                        : 'Aquí verás a todo tu personal y desde aquí podrás registrar contrataciones.'
                "
                @abrir="abrir"
                @ordenar="ordenar"
            >
                <template #celda-estado="{ fila }">
                    <Etiqueta :tono="fila.activo ? 'exito' : 'neutro'">
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

                    <Link v-else href="/empleados/nuevo">
                        <Boton tipo="button">Registrar empleado</Boton>
                    </Link>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="empleados.total > 0"
                :pagina="empleados.pagina"
                :paginas="empleados.paginas"
                :total="empleados.total"
                :desde="empleados.desde"
                :hasta="empleados.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>
    </Armazon>
</template>
