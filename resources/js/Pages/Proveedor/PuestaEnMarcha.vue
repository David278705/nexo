<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * La puesta en marcha: el asistente de carga inicial (documento 3, 5.8, y
 * documento 2, 6.1).
 *
 * Toda la pantalla gira alrededor de una sola diferencia: **«Validar sin cargar»
 * no escribe nada y «Confirmar la carga» sí.** Por eso son dos botones y no uno
 * con una casilla, por eso el segundo no se puede pulsar mientras el informe
 * tenga un solo error, y por eso el informe dice en letras que todavía no se ha
 * guardado nada.
 *
 * El archivo se manda las dos veces. No se guarda en ninguna parte —el Excel
 * solo se usa para cargar (documento 1, 13.1)—, así que el servidor lo vuelve a
 * validar entero antes de escribir en vez de fiarse del informe que se enseñó
 * hace un rato.
 *
 * **En vez de los cuatro pasos del prototipo va la tabla de las seis
 * plantillas.** Una puesta en marcha no es una fila de pasos que se recorre una
 * vez: es el mismo ciclo repetido —subir, validar, corregir, confirmar— hasta
 * seis veces, y a veces en dos reuniones distintas. Lo que hace falta al abrir
 * la pantalla no es saber en qué paso se quedó, sino qué plantillas de esa
 * empresa ya entraron; eso es lo que dice la tabla, con su fecha y sus filas.
 */
const props = defineProps({
    empresas: { type: Array, required: true },
    plantillas: { type: Array, required: true },
    viendo: { type: Object, required: true },
    estadoDeLaEmpresa: { type: Object, default: null },
    historial: { type: Array, default: () => [] },
    informe: { type: Object, default: null },
    notaDeLaTransaccion: { type: String, required: true },
    notaDelOrden: { type: String, required: true },
});

const RUTA = "/proveedor/puesta-en-marcha";

const formulario = useForm({
    empresa: props.viendo.empresa ?? "",
    plantilla: props.viendo.plantilla,
    archivo: null,
});

/*
 * La empresa y la plantilla viven en la dirección, no en el navegador. Cambiar
 * cualquiera de las dos vuelve a pedir la pantalla, y eso borra el informe
 * anterior, que es justo lo que tiene que pasar: un informe es de un archivo y
 * de una plantilla concretos, y dejarlo en pantalla después de cambiar de
 * plantilla invita a confirmar la carga equivocada.
 *
 * `preserveState` mantiene el archivo ya elegido, que es lo único que no cabe en
 * la dirección.
 */
function pedir(cambios) {
    router.get(
        RUTA,
        { ...props.viendo, ...cambios },
        { preserveScroll: true, preserveState: true },
    );
}

watch(
    () => props.viendo,
    (viendo) => {
        formulario.empresa = viendo.empresa ?? "";
        formulario.plantilla = viendo.plantilla;
    },
);

function elegirArchivo(evento) {
    formulario.archivo = evento.target.files[0] ?? null;
}

const hayArchivo = computed(() => formulario.archivo !== null);

const hayEmpresa = computed(() => formulario.empresa !== "");

const nombreElegido = computed(
    () =>
        props.plantillas.find(
            (plantilla) => plantilla.valor === props.viendo.plantilla,
        )?.texto ?? "",
);

// El informe es de la plantilla que se acaba de validar. Si mientras tanto se
// eligió otra, deja de servir para confirmar nada.
const informeAlDia = computed(
    () =>
        props.informe !== null &&
        props.informe.plantilla === nombreElegido.value,
);

const sePuedeConfirmar = computed(
    () =>
        hayEmpresa.value &&
        hayArchivo.value &&
        informeAlDia.value &&
        props.informe.filasConError === 0,
);

/*
 * El resumen del informe con las palabras del prototipo. Las tres cifras van
 * juntas en una frase porque juntas son la decisión: 34 leídas, 31 correctas y 3
 * con error significa que hay que corregir el archivo, no que se puedan cargar
 * 31.
 */
const resumenDelInforme = computed(() => {
    if (props.informe === null) {
        return "";
    }

    return `${props.informe.filasLeidas} filas leídas · ${props.informe.filasCorrectas} correctas · ${props.informe.filasConError} con error.`;
});

const columnasDelInforme = [
    { clave: "fila", titulo: "Fila", numerica: true },
    { clave: "columna", titulo: "Columna" },
    { clave: "motivo", titulo: "Problema" },
];

const columnasDelHistorial = [
    { clave: "fecha", titulo: "Fecha" },
    { clave: "empresa", titulo: "Empresa" },
    { clave: "plantilla", titulo: "Plantilla" },
    { clave: "filas", titulo: "Filas", numerica: true },
    { clave: "quien", titulo: "Quién" },
];

const columnasDelEstado = [
    { clave: "nombre", titulo: "Plantilla" },
    { clave: "estado", titulo: "Estado" },
    { clave: "filas", titulo: "Filas", numerica: true },
    { clave: "fecha", titulo: "Cargada el" },
    { clave: "quien", titulo: "Quién" },
];

const filasDelEstado = computed(() =>
    (props.estadoDeLaEmpresa?.plantillas ?? []).map((plantilla) => ({
        id: plantilla.clave,
        nombre: plantilla.nombre,
        obligatoria: plantilla.obligatoria,
        cargada: plantilla.cargada,
        filas: plantilla.cargada?.filas ?? "—",
        fecha: plantilla.cargada?.fecha ?? "—",
        quien: plantilla.cargada?.quien ?? "—",
    })),
);

/*
 * El aviso del orden de carga (documento 2, 6.3) solo cuando viene al caso: la
 * plantilla elegida no es la obligatoria y esa empresa todavía no tiene planta.
 *
 * Cuál es la obligatoria lo dice el servidor en cada fila, no una clave escrita
 * aquí: la pantalla no tiene por qué saberse el nombre interno de ninguna de las
 * seis.
 */
const faltaLaPlanta = computed(() => {
    if (props.estadoDeLaEmpresa === null || props.estadoDeLaEmpresa.plantaCargada) {
        return false;
    }

    const elegida = props.estadoDeLaEmpresa.plantillas.find(
        (plantilla) => plantilla.clave === props.viendo.plantilla,
    );

    return elegida !== undefined && !elegida.obligatoria;
});

function validar() {
    formulario.post(`${RUTA}/simular`, {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
    });
}

function confirmar() {
    formulario.post(`${RUTA}/cargar`, {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Puesta en marcha" />

    <ArmazonProveedor>
        <div>
            <h1 class="text-titulo font-semibold text-texto">
                Puesta en marcha
            </h1>
            <p class="mt-1 text-apoyo text-texto-secundario">
                Carga inicial de información desde las plantillas de Excel · Solo
                la ejecuta el proveedor
            </p>
        </div>

        <div
            class="mt-5 rounded-[var(--radius-modal)] border border-borde bg-superficie p-4"
        >
            <div class="grid gap-4 md:grid-cols-3">
                <Selector
                    :model-value="formulario.empresa"
                    etiqueta="Empresa"
                    marcador="Elija la empresa…"
                    :opciones="empresas"
                    :error="formulario.errors.empresa"
                    @update:model-value="pedir({ empresa: $event })"
                />

                <Selector
                    :model-value="formulario.plantilla"
                    etiqueta="Plantilla"
                    :opciones="plantillas"
                    :con-marcador="false"
                    :error="formulario.errors.plantilla"
                    @update:model-value="pedir({ plantilla: $event })"
                />

                <div>
                    <label
                        for="archivo-de-carga"
                        class="block text-apoyo font-medium text-texto-secundario"
                    >
                        Archivo diligenciado
                    </label>
                    <input
                        id="archivo-de-carga"
                        type="file"
                        accept=".xlsx,.xls"
                        :aria-invalid="formulario.errors.archivo ? 'true' : undefined"
                        class="mt-1 block h-control w-full rounded-[var(--radius-caja)] border bg-superficie px-3 py-1.5 text-tabla text-texto"
                        :class="
                            formulario.errors.archivo
                                ? 'border-error'
                                : 'border-borde'
                        "
                        @change="elegirArchivo"
                    />
                    <p
                        v-if="formulario.errors.archivo"
                        class="mt-1 text-apoyo text-error"
                    >
                        {{ formulario.errors.archivo }}
                    </p>
                </div>
            </div>

            <Aviso v-if="faltaLaPlanta" tono="advertencia" class="mt-4">
                {{ notaDelOrden }}
            </Aviso>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <Boton
                    tipo="button"
                    variante="secundario"
                    :enviando="formulario.processing"
                    :deshabilitado="!hayEmpresa || !hayArchivo"
                    @click="validar"
                >
                    Validar sin cargar
                </Boton>

                <Boton
                    tipo="button"
                    :enviando="formulario.processing"
                    :deshabilitado="!sePuedeConfirmar"
                    @click="confirmar"
                >
                    Confirmar la carga
                </Boton>

                <p v-if="!sePuedeConfirmar" class="text-apoyo text-texto-terciario">
                    Primero valide el archivo. Se confirma cuando el informe no
                    tenga ningún error.
                </p>
            </div>
        </div>

        <div v-if="informe" class="mt-4">
            <Aviso :tono="informe.filasConError > 0 ? 'advertencia' : 'exito'">
                <b>{{ resumenDelInforme }}</b>
                {{
                    informe.filasConError > 0
                        ? "Nada se ha guardado todavía. Corrija el archivo y vuelva a validar."
                        : "Nada se ha guardado todavía: confirme la carga para que entre."
                }}
            </Aviso>

            <div v-if="informe.errores.length > 0" class="mt-3">
                <TablaDatos
                    :columnas="columnasDelInforme"
                    :filas="informe.errores"
                    clave="texto"
                    :clicable="false"
                >
                    <template #celda-columna="{ fila }">
                        {{ fila.columna ?? "—" }}
                    </template>
                </TablaDatos>
            </div>
        </div>

        <Aviso tono="informacion" class="mt-4">
            {{ notaDeLaTransaccion }}
        </Aviso>

        <div v-if="estadoDeLaEmpresa" class="mt-6">
            <h2 class="text-seccion font-semibold text-texto">
                {{ estadoDeLaEmpresa.empresa }}
            </h2>
            <p class="mt-1 text-apoyo text-texto-secundario">
                Qué plantillas entraron ya y cuáles faltan.
            </p>

            <div class="mt-3">
                <TablaDatos
                    :columnas="columnasDelEstado"
                    :filas="filasDelEstado"
                    :clicable="false"
                >
                    <template #celda-nombre="{ fila }">
                        {{ fila.nombre }}
                        <span
                            v-if="fila.obligatoria"
                            class="text-apoyo text-texto-terciario"
                        >
                            · obligatoria
                        </span>
                    </template>
                    <template #celda-estado="{ fila }">
                        <Etiqueta :tono="fila.cargada ? 'exito' : 'neutro'">
                            {{ fila.cargada ? "Cargada" : "Sin cargar" }}
                        </Etiqueta>
                    </template>
                </TablaDatos>
            </div>
        </div>

        <div class="mt-6">
            <h2 class="text-seccion font-semibold text-texto">
                Historial de cargas
            </h2>

            <div class="mt-3">
                <TablaDatos
                    :columnas="columnasDelHistorial"
                    :filas="historial"
                    :clicable="false"
                    titulo-vacio="Todavía no se ha cargado ninguna plantilla"
                    descripcion-vacio="Aquí queda la constancia de cada carga: qué archivo, cuántas filas, quién y cuándo."
                />
            </div>
        </div>
    </ArmazonProveedor>
</template>
