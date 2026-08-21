<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import ModalDeCatalogo from "@/Components/Proveedor/ModalDeCatalogo.vue";
import TablaDeCatalogo from "@/Components/Proveedor/TablaDeCatalogo.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Modal from "@/Components/Modal.vue";
import Pestanas from "@/Components/Pestanas.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * Los catálogos del sistema (documento 4, capítulo 8).
 *
 * Cinco pestañas: tres que escriben —festivos, entidades y ciudades— y dos que
 * solo leen —constantes legales y umbrales—. Las dos que solo leen no están a
 * medias: los números legales viven en la configuración porque un error en ellos
 * calcula mal en todas las empresas a la vez, y los umbrales que faltan no se
 * inventan aquí. Cada una lo dice en su propia nota, escrita en el servidor.
 *
 * Ninguna acción de esta pantalla borra nada. Desactivar es la acción de las
 * entidades y de los municipios, porque hay empleados apuntando a ellos, y
 * quitar un festivo es un borrado lógico que queda en la auditoría.
 *
 * La pestaña, la lista y el año que se están mirando van en la dirección, no en
 * el navegador: así se vuelve a donde se estaba después de cada alta, que es lo
 * que hace falta cuando se carga un calendario entero de a un festivo.
 */
const props = defineProps({
    pestanas: { type: Array, required: true },
    viendo: { type: Object, required: true },
    aviso: { type: Object, default: null },
    constantes: { type: Object, required: true },
    festivos: { type: Object, required: true },
    entidades: { type: Object, required: true },
    ciudades: { type: Object, required: true },
    umbrales: { type: Object, required: true },
});

const RUTA = "/proveedor/catalogos";

const activa = computed({
    get: () => props.viendo.pestana,
    set: (valor) => pedir({ pestana: valor }),
});

function pedir(cambios) {
    router.get(
        RUTA,
        { ...props.viendo, ...cambios },
        { preserveScroll: true, preserveState: true },
    );
}

/*
 * Las tres altas comparten modal y comparten esta pareja de variables: qué
 * formulario está abierto y sobre qué fila. `edicion` en nulo significa que se
 * está agregando, no corrigiendo.
 */
const abierto = ref("");
const edicion = ref(null);

const formularioDeEntidad = useForm({ codigo: "", nombre: "" });
const formularioDeCiudad = useForm({
    departamento_id: "",
    nombre: "",
    codigo_dane: "",
});
const formularioDeFestivo = useForm({ fecha: "", nombre: "" });

const camposDeEntidad = computed(() => [
    {
        clave: "codigo",
        etiqueta: props.entidades.codigo,
        opcional: true,
        ayuda: "No todas las listas tienen código oficial.",
    },
    {
        clave: "nombre",
        etiqueta: "Nombre",
        ayuda: "Es lo que ve y elige quien registra un empleado.",
    },
]);

const camposDeCiudad = [
    { clave: "nombre", etiqueta: "Municipio" },
    {
        clave: "codigo_dane",
        etiqueta: "Código DANE",
        opcional: true,
        ayuda: "Cinco dígitos: los dos del departamento y los tres del municipio.",
    },
];

const camposDeFestivo = [
    { clave: "fecha", etiqueta: "Fecha", tipo: "fecha" },
    {
        clave: "nombre",
        etiqueta: "Festivo",
        ayuda: "El nombre es lo que permite revisar el calendario contra el oficial.",
    },
];

function abrirEntidad(fila = null) {
    edicion.value = fila;
    formularioDeEntidad.clearErrors();
    formularioDeEntidad.codigo = fila ? fila.codigoValor : "";
    formularioDeEntidad.nombre = fila ? fila.nombre : "";
    abierto.value = "entidad";
}

function guardarEntidad() {
    const base = `${RUTA}/entidades/${props.viendo.entidad}`;
    const opciones = { preserveScroll: true, onSuccess: cerrar };

    if (edicion.value) {
        formularioDeEntidad.put(`${base}/${edicion.value.id}`, opciones);
        return;
    }

    formularioDeEntidad.post(base, opciones);
}

function abrirCiudad(fila = null) {
    edicion.value = fila;
    formularioDeCiudad.clearErrors();
    formularioDeCiudad.departamento_id = props.viendo.departamento;
    formularioDeCiudad.nombre = fila ? fila.nombre : "";
    formularioDeCiudad.codigo_dane = fila ? fila.codigoValor : "";
    abierto.value = "ciudad";
}

function guardarCiudad() {
    const opciones = { preserveScroll: true, onSuccess: cerrar };

    if (edicion.value) {
        formularioDeCiudad.put(
            `${RUTA}/ciudades/${edicion.value.id}`,
            opciones,
        );
        return;
    }

    formularioDeCiudad.post(`${RUTA}/ciudades`, opciones);
}

function abrirFestivo() {
    edicion.value = null;
    formularioDeFestivo.clearErrors();
    formularioDeFestivo.fecha = "";
    formularioDeFestivo.nombre = "";
    abierto.value = "festivo";
}

function guardarFestivo() {
    formularioDeFestivo.post(`${RUTA}/festivos`, {
        preserveScroll: true,
        onSuccess: cerrar,
    });
}

// Quitar un festivo cambia el conteo de días hábiles de todas las empresas, así
// que la confirmación dice qué pasa y no «¿está seguro?».
const porQuitar = ref(null);

function quitarFestivo() {
    router.delete(`${RUTA}/festivos/${porQuitar.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            porQuitar.value = null;
        },
    });
}

function alternarEntidad(fila) {
    router.post(
        `${RUTA}/entidades/${props.viendo.entidad}/${fila.id}/estado`,
        {},
        { preserveScroll: true },
    );
}

function alternarCiudad(fila) {
    router.post(
        `${RUTA}/ciudades/${fila.id}/estado`,
        {},
        { preserveScroll: true },
    );
}

function cerrar() {
    abierto.value = "";
    edicion.value = null;
}

const columnasDeConstantes = [
    { clave: "constante", titulo: "Constante" },
    { clave: "valor", titulo: "Valor", numerica: true },
    { clave: "norma", titulo: "Norma que la respalda" },
    { clave: "verificada", titulo: "Verificada" },
];

const columnasDeFestivos = [
    { clave: "fecha", titulo: "Fecha" },
    { clave: "dia", titulo: "Día" },
    { clave: "nombre", titulo: "Festivo" },
    { clave: "acciones", titulo: "Acciones" },
];

const columnasDeUmbrales = [
    { clave: "umbral", titulo: "Umbral" },
    { clave: "valor", titulo: "Valor" },
    { clave: "donde", titulo: "Dónde vive" },
    { clave: "estado", titulo: "Estado" },
];

const listaActual = computed(
    () =>
        props.entidades.listas.find(
            (lista) => lista.valor === props.viendo.entidad,
        )?.texto ?? "",
);
</script>

<template>
    <Head title="Catálogos" />

    <ArmazonProveedor>
        <h1 class="text-titulo font-semibold text-texto">Catálogos</h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            Datos que cambian con el tiempo y que no pueden vivir en el código
        </p>

        <Aviso v-if="aviso" :tono="aviso.tono" class="mt-4">
            {{ aviso.texto }}
        </Aviso>

        <div class="mt-5">
            <Pestanas v-model="activa" :pestanas="pestanas" />

            <div
                :id="`panel-${viendo.pestana}`"
                role="tabpanel"
                :aria-labelledby="`pestana-${viendo.pestana}`"
                class="mt-4"
            >
                <!-- Constantes legales -->
                <div v-if="viendo.pestana === 'constantes'">
                    <TablaDatos
                        :columnas="columnasDeConstantes"
                        :filas="constantes.filas"
                        clave="constante"
                        :clicable="false"
                        titulo-vacio="No hay constantes declaradas"
                    />

                    <Aviso class="mt-4">{{ constantes.nota }}</Aviso>
                </div>

                <!-- Festivos -->
                <div v-else-if="viendo.pestana === 'festivos'">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="w-48">
                            <Selector
                                :model-value="String(viendo.anio)"
                                etiqueta="Año"
                                :con-marcador="false"
                                :opciones="
                                    festivos.anios.map((anio) => ({
                                        valor: String(anio),
                                        texto: String(anio),
                                    }))
                                "
                                @update:model-value="
                                    pedir({ anio: Number($event) })
                                "
                            />
                        </div>

                        <Boton tipo="button" @click="abrirFestivo">
                            + Agregar festivo
                        </Boton>
                    </div>

                    <div class="mt-4">
                        <TablaDatos
                            :columnas="columnasDeFestivos"
                            :filas="festivos.filas"
                            :clicable="false"
                            :titulo-vacio="`El calendario de ${viendo.anio} está vacío`"
                            descripcion-vacio="Sin festivos cargados, los días hábiles de ese año se cuentan como si no hubiera ninguno."
                        >
                            <template #celda-nombre="{ fila }">
                                <span>{{ fila.nombre }}</span>
                                <Etiqueta v-if="fila.enSabado" tono="advertencia" class="ml-2">
                                    En sábado
                                </Etiqueta>
                            </template>

                            <template #celda-acciones="{ fila }">
                                <Boton
                                    tipo="button"
                                    variante="texto"
                                    @click="porQuitar = fila"
                                >
                                    Quitar
                                </Boton>
                            </template>
                        </TablaDatos>
                    </div>

                    <p class="mt-3 text-apoyo text-texto-terciario">
                        Los festivos que caen en sábado van marcados: son los que
                        más se olvidan al cargar el año y los únicos que solo
                        cambian el cálculo en las empresas con sábado laboral.
                    </p>
                </div>

                <!-- EPS y entidades -->
                <div v-else-if="viendo.pestana === 'entidades'">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="w-72">
                            <Selector
                                :model-value="viendo.entidad"
                                etiqueta="Lista"
                                :con-marcador="false"
                                :opciones="entidades.listas"
                                @update:model-value="pedir({ entidad: $event })"
                            />
                        </div>

                        <Boton tipo="button" @click="abrirEntidad()">
                            + Agregar a {{ listaActual }}
                        </Boton>
                    </div>

                    <div class="mt-4">
                        <TablaDeCatalogo
                            :filas="entidades.filas"
                            :titulo-codigo="entidades.codigo"
                            :titulo-vacio="`${listaActual} no tiene ninguna entidad cargada`"
                            descripcion-vacio="Mientras la lista esté vacía, ese campo no ofrece ninguna opción en el registro de empleado."
                            @editar="abrirEntidad"
                            @alternar="alternarEntidad"
                        />
                    </div>

                    <p class="mt-3 text-apoyo text-texto-terciario">
                        Una entidad que desaparece se desactiva, no se borra:
                        deja de ofrecerse en los formularios y sigue leyéndose en
                        los empleados que ya la tenían.
                    </p>
                </div>

                <!-- Ciudades -->
                <div v-else-if="viendo.pestana === 'ciudades'">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="w-72">
                            <Selector
                                :model-value="String(viendo.departamento)"
                                etiqueta="Departamento"
                                :con-marcador="false"
                                :opciones="
                                    ciudades.departamentos.map((uno) => ({
                                        valor: String(uno.valor),
                                        texto: uno.texto,
                                    }))
                                "
                                @update:model-value="
                                    pedir({ departamento: Number($event) })
                                "
                            />
                        </div>

                        <Boton tipo="button" @click="abrirCiudad()">
                            + Agregar municipio
                        </Boton>
                    </div>

                    <div class="mt-4">
                        <TablaDeCatalogo
                            :filas="ciudades.filas"
                            titulo-codigo="Código DANE"
                            titulo-vacio="Ese departamento no tiene municipios cargados"
                            @editar="abrirCiudad"
                            @alternar="alternarCiudad"
                        />
                    </div>

                    <p class="mt-3 text-apoyo text-texto-terciario">
                        Se miran de a un departamento, como se eligen en el
                        formulario de empleado. Los códigos DANE en blanco se
                        completan cuando se contraste la lista con el archivo
                        oficial.
                    </p>
                </div>

                <!-- Umbrales -->
                <div v-else>
                    <TablaDatos
                        :columnas="columnasDeUmbrales"
                        :filas="umbrales.filas"
                        clave="umbral"
                        :clicable="false"
                        titulo-vacio="No hay umbrales declarados"
                    >
                        <template #celda-estado="{ fila }">
                            <Etiqueta :tono="fila.tono">
                                {{ fila.estado }}
                            </Etiqueta>
                        </template>
                    </TablaDatos>

                    <Aviso class="mt-4">{{ umbrales.nota }}</Aviso>
                </div>
            </div>
        </div>

        <ModalDeCatalogo
            :abierto="abierto === 'entidad'"
            :titulo="
                edicion
                    ? `Editar ${edicion.nombre}`
                    : `Agregar a ${listaActual}`
            "
            :campos="camposDeEntidad"
            :formulario="formularioDeEntidad"
            :texto-boton="edicion ? 'Guardar cambios' : 'Agregar'"
            @cerrar="cerrar"
            @guardar="guardarEntidad"
        />

        <ModalDeCatalogo
            :abierto="abierto === 'ciudad'"
            :titulo="edicion ? `Editar ${edicion.nombre}` : 'Agregar municipio'"
            :campos="camposDeCiudad"
            :formulario="formularioDeCiudad"
            :texto-boton="edicion ? 'Guardar cambios' : 'Agregar'"
            @cerrar="cerrar"
            @guardar="guardarCiudad"
        />

        <ModalDeCatalogo
            :abierto="abierto === 'festivo'"
            titulo="Agregar festivo"
            :campos="camposDeFestivo"
            :formulario="formularioDeFestivo"
            texto-boton="Agregar al calendario"
            @cerrar="cerrar"
            @guardar="guardarFestivo"
        />

        <Modal
            :abierto="porQuitar !== null"
            titulo="Quitar del calendario"
            @cerrar="porQuitar = null"
        >
            <p class="text-cuerpo text-texto">
                Se quita
                <strong>{{ porQuitar?.nombre }}</strong> del
                {{ porQuitar?.fecha }}. Ese día vuelve a contar como hábil en el
                cálculo de vacaciones de todas las empresas y en el corrimiento
                de las alertas.
            </p>
            <p class="mt-3 text-apoyo text-texto-secundario">
                No se borra de verdad: queda registrado en la auditoría y volver
                a cargar esa fecha lo restituye.
            </p>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="porQuitar = null">
                    Cancelar
                </Boton>
                <Boton
                    variante="destructivo"
                    tipo="button"
                    @click="quitarFestivo"
                >
                    Quitar del calendario
                </Boton>
            </template>
        </Modal>
    </ArmazonProveedor>
</template>
