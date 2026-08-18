<script setup>
import { ref } from "vue";
import { Head } from "@inertiajs/vue3";
import Aviso from "@/Components/Aviso.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Modal from "@/Components/Modal.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Selector from "@/Components/Selector.vue";
import SelectorUbicacion from "@/Components/SelectorUbicacion.vue";
import TablaDatos from "@/Components/TablaDatos.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * Las ubicaciones llegan del catálogo real —los mil cien municipios cargados—,
 * no de datos de mentira: la regla del país encadenado solo se comprueba de
 * verdad probándola con la lista completa.
 */
defineProps({
    paises: { type: Array, default: () => [] },
    departamentos: { type: Array, default: () => [] },
    codigoDeColombia: { type: String, default: "CO" },
});

/*
 * Página de muestra del sistema de diseño. No es una pantalla del producto: es
 * el catálogo con el que se comprueba que los once componentes existen, se ven
 * como el prototipo y no traen ni un color literal. La ruta solo se registra
 * fuera de producción.
 */

/*
 * Las muestras nombran la variable, no el valor: si aquí figurara el número
 * hexadecimal habría dos verdades sobre el mismo color, y tarde o temprano una
 * de las dos se quedaría vieja.
 */
const paleta = [
    { nombre: "Marca / navegación", clase: "bg-marca" },
    { nombre: "Acción", clase: "bg-accion" },
    { nombre: "Éxito", clase: "bg-exito" },
    { nombre: "Advertencia", clase: "bg-advertencia" },
    { nombre: "Error", clase: "bg-error" },
    { nombre: "Texto principal", clase: "bg-texto" },
    { nombre: "Texto secundario", clase: "bg-texto-secundario" },
    { nombre: "Bordes", clase: "bg-borde" },
    { nombre: "Fondo de la aplicación", clase: "bg-fondo" },
];

const tipografia = [
    { clase: "text-titulo", nombre: "Título de pantalla", medida: "24 px" },
    { clase: "text-seccion", nombre: "Título de sección", medida: "18 px" },
    { clase: "text-cuerpo", nombre: "Texto general", medida: "15 px" },
    { clase: "text-tabla", nombre: "Texto de tabla", medida: "14 px" },
    { clase: "text-apoyo", nombre: "Etiquetas y apoyo", medida: "13 px" },
];

const columnas = [
    { clave: "documento", titulo: "Documento" },
    { clave: "nombre", titulo: "Nombre" },
    { clave: "estado", titulo: "Estado" },
    { clave: "saldo", titulo: "Saldo de vacaciones", numerica: true },
];

const filas = [
    {
        id: 1,
        documento: "1.020.334.556",
        nombre: "Ana Torres",
        estado: "Activo",
        saldo: "17,25",
        tono: "exito",
    },
    {
        id: 2,
        documento: "79.443.221",
        nombre: "Carlos Ramírez",
        estado: "Por vencer",
        saldo: "4,25",
        tono: "advertencia",
    },
    {
        id: 3,
        documento: "52.118.907",
        nombre: "Diana Peña",
        estado: "Inactivo",
        saldo: "0,00",
        tono: "neutro",
    },
];

const ciudades = [
    "Armenia",
    "Barranquilla",
    "Bogotá",
    "Bucaramanga",
    "Cali",
    "Cartagena",
    "Cúcuta",
    "Ibagué",
    "Manizales",
    "Medellín",
    "Neiva",
    "Pereira",
    "Santa Marta",
    "Villavicencio",
].map((ciudad) => ({ valor: ciudad, texto: ciudad }));

const tiposDeContrato = [
    { valor: "indefinido", texto: "Término indefinido" },
    { valor: "fijo", texto: "Término fijo" },
    { valor: "obra", texto: "Obra o labor" },
];

const nombre = ref("");
const documento = ref("123");
const fecha = ref("2026-04-15");
const ciudad = ref("");
const tipoDeContrato = ref("fijo");
const ubicacion = ref({ pais: "", departamento: "", ciudad: "" });

const filtros = ref([
    { clave: "estado", texto: "Estado: Activo" },
    { clave: "contrato", texto: "Contrato: Término fijo" },
]);

const modalCorto = ref(false);
const modalAncho = ref(false);
const aviso = ref("");

function mostrarNotificacion() {
    aviso.value = "";
    setTimeout(() => {
        aviso.value = "Se guardó la incapacidad de Ana Torres.";
    }, 0);
}
</script>

<template>
    <Head title="Sistema de diseño" />

    <main class="mx-auto max-w-contenido bg-fondo px-6 py-8">
        <h1 class="text-titulo font-semibold text-texto">Sistema de diseño</h1>
        <p class="mt-1 text-cuerpo text-texto-secundario">
            Las piezas con las que se construyen todas las pantallas. Ninguna
            escribe un color: todas leen las variables de
            <code>resources/css/app.css</code>.
        </p>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">Color</h2>
            <div
                class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"
            >
                <div
                    v-for="color in paleta"
                    :key="color.nombre"
                    class="overflow-hidden rounded-[var(--radius-caja)] border border-borde bg-superficie"
                >
                    <div class="h-12" :class="color.clase" />
                    <div class="px-3 py-2">
                        <p class="text-apoyo font-medium text-texto">
                            {{ color.nombre }}
                        </p>
                        <p class="text-apoyo text-texto-terciario">
                            {{ color.clase }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">
                Tipografía · Inter
            </h2>
            <div
                class="mt-3 space-y-2 rounded-[var(--radius-modal)] border border-borde bg-superficie p-4"
            >
                <p
                    v-for="linea in tipografia"
                    :key="linea.clase"
                    :class="linea.clase"
                    class="text-texto"
                >
                    {{ linea.nombre }} · {{ linea.medida }}
                </p>
                <p class="text-cifra font-semibold tabular-nums text-texto">
                    32 px · cifra de indicador
                </p>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">Botones</h2>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <Boton tipo="button">Nuevo</Boton>
                <Boton tipo="button" variante="secundario">Exportar</Boton>
                <Boton tipo="button" variante="texto">Cancelar</Boton>
                <Boton tipo="button" variante="destructivo">Eliminar</Boton>
                <Boton tipo="button" enviando>Guardando…</Boton>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">
                Campos y selectores
            </h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <CampoTexto
                    v-model="nombre"
                    etiqueta="Primer nombre"
                    ayuda="Como aparece en el documento."
                />
                <CampoTexto
                    v-model="documento"
                    etiqueta="Documento"
                    error="El documento 123 no corresponde a ningún empleado activo."
                />
                <CampoFecha v-model="fecha" etiqueta="Fecha de ingreso" />
                <Selector
                    v-model="tipoDeContrato"
                    etiqueta="Tipo de contrato"
                    :opciones="tiposDeContrato"
                />
                <Selector
                    v-model="ciudad"
                    etiqueta="Ciudad"
                    :opciones="ciudades"
                    ayuda="Con más de diez opciones aparece la búsqueda sola."
                />
                <CampoTexto
                    v-model="nombre"
                    etiqueta="Segundo apellido"
                    opcional
                />
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">
                País, departamento y ciudad
            </h2>
            <p class="mt-1 text-apoyo text-texto-secundario">
                Con Colombia, las listas se encadenan: al elegir el departamento
                se cargan sus municipios. Con cualquier otro país, los dos
                campos se digitan.
            </p>
            <div class="mt-3">
                <SelectorUbicacion
                    v-model="ubicacion"
                    :paises="paises"
                    :departamentos="departamentos"
                    :codigo-de-colombia="codigoDeColombia"
                    sufijo="de nacimiento"
                />
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">
                Etiquetas de estado
            </h2>
            <div class="mt-3 flex flex-wrap gap-3">
                <Etiqueta tono="exito">Activo</Etiqueta>
                <Etiqueta tono="neutro">Inactivo</Etiqueta>
                <Etiqueta tono="advertencia">Por vencer</Etiqueta>
                <Etiqueta tono="error">Vencido</Etiqueta>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">Avisos</h2>
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                <Aviso tono="informacion">
                    El periodo de prueba de Ana Torres termina el 14 de mayo.
                </Aviso>
                <Aviso tono="exito">Se registró la incapacidad.</Aviso>
                <Aviso tono="advertencia" titulo="Saldo insuficiente">
                    Faltan 1,25 días para cubrir estas vacaciones.
                </Aviso>
                <Aviso tono="error">
                    El documento 12345 no corresponde a ningún empleado activo.
                </Aviso>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">
                Tarjetas de indicador
            </h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <TarjetaIndicador valor="24" etiqueta="Empleados activos" />
                <TarjetaIndicador
                    valor="3"
                    etiqueta="Contratos por vencer"
                    variacion="2 más que el mes pasado"
                    tendencia="sube"
                />
                <TarjetaIndicador
                    valor="1,8 %"
                    etiqueta="Ausentismo"
                    variacion="0,4 puntos menos"
                    tendencia="baja"
                />
                <TarjetaIndicador valor="312,50" etiqueta="Días acumulados" />
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">
                Barra de filtros y tabla
            </h2>
            <div class="mt-3">
                <BarraFiltros
                    :aplicados="filtros"
                    @quitar="
                        filtros = filtros.filter((f) => f.clave !== $event)
                    "
                    @limpiar="filtros = []"
                >
                    <CampoTexto v-model="nombre" etiqueta="Buscar" />
                    <Selector
                        v-model="tipoDeContrato"
                        etiqueta="Tipo de contrato"
                        :opciones="tiposDeContrato"
                    />
                </BarraFiltros>

                <TablaDatos :columnas="columnas" :filas="filas">
                    <template #celda-estado="{ fila }">
                        <Etiqueta :tono="fila.tono">{{ fila.estado }}</Etiqueta>
                    </template>
                </TablaDatos>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-seccion font-semibold text-texto">Estado vacío</h2>
            <div
                class="mt-3 rounded-[var(--radius-modal)] border border-borde bg-superficie"
            >
                <EstadoVacio
                    titulo="Aún no has registrado incapacidades"
                    descripcion="Aquí quedan las incapacidades de tu equipo, con los días corridos ya calculados."
                >
                    <Boton tipo="button">Registrar incapacidad</Boton>
                </EstadoVacio>
            </div>
        </section>

        <section class="mt-8 mb-4">
            <h2 class="text-seccion font-semibold text-texto">
                Modal y notificación
            </h2>
            <div class="mt-3 flex flex-wrap gap-3">
                <Boton tipo="button" @click="modalCorto = true">
                    Modal corto (560 px)
                </Boton>
                <Boton
                    tipo="button"
                    variante="secundario"
                    @click="modalAncho = true"
                >
                    Modal ancho (800 px)
                </Boton>
                <Boton
                    tipo="button"
                    variante="secundario"
                    @click="mostrarNotificacion"
                >
                    Mostrar notificación
                </Boton>
            </div>
        </section>

        <Modal
            :abierto="modalCorto"
            titulo="Registrar una incapacidad"
            @cerrar="modalCorto = false"
        >
            <CampoTexto
                v-model="documento"
                etiqueta="Documento del empleado"
                ayuda="Se valida al salir del campo."
            />
            <template #pie>
                <Boton
                    tipo="button"
                    variante="texto"
                    @click="modalCorto = false"
                >
                    Cancelar
                </Boton>
                <Boton tipo="button" @click="modalCorto = false">
                    Continuar
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="modalAncho"
            titulo="Incapacidad de Ana Torres"
            ancho="ancho"
            @cerrar="modalAncho = false"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <CampoFecha v-model="fecha" etiqueta="Fecha de inicio" />
                <CampoFecha v-model="fecha" etiqueta="Fecha de fin" />
            </div>
            <template #pie>
                <Boton
                    tipo="button"
                    variante="texto"
                    @click="modalAncho = false"
                >
                    Cancelar
                </Boton>
                <Boton tipo="button" @click="modalAncho = false">
                    Guardar
                </Boton>
            </template>
        </Modal>

        <Notificacion :mensaje="aviso" @cerrar="aviso = ''" />
    </main>
</template>
