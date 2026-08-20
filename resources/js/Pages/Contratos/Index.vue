<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * El listado de contratos (documento 3, sección 5.4).
 *
 * La vista por defecto son los que vencen en los próximos 90 días: la pantalla
 * existe para que nadie se entere de un vencimiento el día después.
 *
 * Nada se filtra aquí. Cada cambio vuelve a pedir la página al servidor con los
 * filtros en la dirección, igual que en el listado de empleados.
 */
const props = defineProps({
    contratos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
    conteos: { type: Object, required: true },
});

const TODOS = "todos";

const columnas = [
    { clave: "empleado", titulo: "Empleado", ordenable: true },
    { clave: "contrato", titulo: "Tipo", ordenable: true },
    { clave: "inicio", titulo: "Inicio", ordenable: true },
    { clave: "vence", titulo: "Vence", ordenable: true },
    { clave: "prorrogas", titulo: "Prórrogas", numerica: true, ordenable: true },
    { clave: "preaviso", titulo: "Límite de preaviso" },
    { clave: "estado", titulo: "Estado" },
];

function pedir(cambios) {
    router.get(
        "/contratos",
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

// Los tres filtros son de lista, así que quitarlos los devuelve a «todos».
function quitar(clave) {
    pedir({ [clave]: TODOS, page: 1 });
}

function limpiar() {
    router.get("/contratos", {}, { preserveScroll: true, replace: true });
}

function abrir(fila) {
    router.visit(`/contratos/${fila.id}`);
}

// No se llama `contratos` a propósito: esa es la prop con las filas, y una
// computada del mismo nombre la taparía en la plantilla sin avisar.
const tiposDeContrato = computed(() => [
    { valor: TODOS, texto: "Todos los contratos" },
    ...props.opciones.contratos,
]);

const urlExportar = computed(() => {
    const parametros = new URLSearchParams();

    Object.entries(props.filtros).forEach(([clave, valor]) => {
        if (valor !== "" && valor !== null && clave !== "porPagina") {
            parametros.append(clave, valor);
        }
    });

    return `/contratos/exportar?${parametros.toString()}`;
});

const textoExportar = computed(() => {
    const uno = props.contratos.total === 1;
    const registros = uno ? "registro" : "registros";
    const filtrados = props.tieneFiltros ? (uno ? " filtrado" : " filtrados") : "";

    return `Exportar ${props.contratos.total} ${registros}${filtrados}`;
});

// El aviso del prototipo: un contrato que venció sin preaviso quedó prorrogado
// solo, y el sistema no puede reflejarlo si nadie lo registra.
const avisoDeVencidos = computed(() => {
    const cuantos = props.conteos.vencidos;

    if (cuantos === 0) {
        return "";
    }

    return cuantos === 1
        ? "1 contrato venció sin registrar prórroga ni terminación."
        : `${cuantos} contratos vencieron sin registrar prórroga ni terminación.`;
});
</script>

<template>
    <Head title="Contratos" />

    <Armazon>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">Contratos</h1>
                <p class="mt-1 text-apoyo text-texto-secundario">
                    Vista por defecto: los que vencen en los próximos
                    {{ conteos.dias }} días · {{ conteos.porVencer }} por vencer
                </p>
            </div>

            <a :href="urlExportar" download>
                <Boton tipo="button" variante="secundario">
                    {{ textoExportar }}
                </Boton>
            </a>
        </div>

        <div v-if="avisoDeVencidos" class="mt-4">
            <Aviso tono="error" :titulo="avisoDeVencidos">
                Bajo la Ley 2466 de 2025, un contrato que vence sin preaviso
                queda prorrogado automáticamente por un término igual al último
                pactado. Conviene registrarlo para que el sistema refleje la
                realidad.
            </Aviso>
        </div>

        <div class="mt-5">
            <BarraFiltros
                :aplicados="aplicados"
                @quitar="quitar"
                @limpiar="limpiar"
            >
                <div class="min-w-[200px]">
                    <Selector
                        :model-value="filtros.vence"
                        etiqueta="Vencimiento"
                        :opciones="opciones.vencimientos"
                        :con-marcador="false"
                        @update:model-value="pedir({ vence: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.contrato"
                        etiqueta="Tipo de contrato"
                        :opciones="tiposDeContrato"
                        marcador="Todos los contratos"
                        @update:model-value="
                            pedir({ contrato: $event, page: 1 })
                        "
                    />
                </div>

                <div class="min-w-[220px]">
                    <Selector
                        :model-value="filtros.prorrogas"
                        etiqueta="Prórrogas"
                        :opciones="opciones.prorrogas"
                        :con-marcador="false"
                        @update:model-value="
                            pedir({ prorrogas: $event, page: 1 })
                        "
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="contratos.filas"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                :titulo-vacio="
                    tieneFiltros
                        ? 'Ningún contrato coincide con estos filtros.'
                        : 'Aún no hay contratos registrados.'
                "
                :descripcion-vacio="
                    tieneFiltros
                        ? 'Con la vista por defecto solo se ven los que vencen en los próximos 90 días. Quita los filtros para verlos todos.'
                        : 'Aquí verás los contratos por vencer, sus prórrogas y la fecha límite para avisar.'
                "
                @abrir="abrir"
                @ordenar="ordenar"
            >
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
                v-if="contratos.total > 0"
                :pagina="contratos.pagina"
                :paginas="contratos.paginas"
                :total="contratos.total"
                :desde="contratos.desde"
                :hasta="contratos.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>
    </Armazon>
</template>
