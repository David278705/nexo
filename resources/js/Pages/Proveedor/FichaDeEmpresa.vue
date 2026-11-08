<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import CampoDinero from "@/Components/CampoDinero.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Modal from "@/Components/Modal.vue";
import Pestanas from "@/Components/Pestanas.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";
import { usarAccionPorFila } from "@/Utilidades/usarAccionPorFila.js";

/*
 * La ficha de una empresa cliente (documento 4, 4.3).
 *
 * Siete pestañas, todas con contenido. «Uso» cuenta las novedades registradas
 * por mes y los módulos que la empresa usa de verdad; «Alertas» enseña el
 * registro real de lo que el motor de avisos le ha enviado, lo mismo que la
 * consola pero acotado a esta empresa (REV-12).
 *
 * La pestaña «Suscripción» es la única con acciones sobre el cobro de este
 * cliente —registrar un pago, cambiarle el plan, suspender y reactivar—, y está
 * aquí y no en la pantalla de Suscripciones porque es aquí donde se mira un
 * cliente con su historial delante. Las tres van por modal y ninguna es un
 * «¿está seguro?»: cada una explica su consecuencia antes de pedir confirmar.
 *
 * La pestaña de parámetros es la única que escribe, y escribe en la
 * configuración de un cliente: por eso es un formulario con su botón y no tres
 * selectores que guardan solos.
 *
 * Ninguna acción de la pestaña de usuarios enseña una contraseña. No existe
 * forma de verla, y eso no es una omisión de esta pantalla: es la promesa del
 * documento y no hay nada en el servidor que la devuelva.
 */
const props = defineProps({
    empresa: { type: Object, required: true },
    cifras: { type: Array, default: () => [] },
    pestanas: { type: Array, required: true },
    resumen: { type: Object, required: true },
    parametros: { type: Array, required: true },
    perfil: { type: Object, required: true },
    usuarios: { type: Array, default: () => [] },
    uso: { type: Object, required: true },
    alertas: { type: Object, required: true },
    suscripcion: { type: Object, required: true },
    bitacora: { type: Object, required: true },
    acceso: { type: Object, required: true },
    volver: { type: String, required: true },
});

const activa = ref("resumen");

const CLAVE_DEL_PERFIL = "perfil_sociodemografico_habilitado";

const formulario = useForm(
    Object.fromEntries(
        props.parametros.map((parametro) => [parametro.clave, parametro.valor]),
    ),
);

const avisoDelPerfil = computed(
    () =>
        formulario[CLAVE_DEL_PERFIL] === "si" &&
        !props.perfil.habilitado &&
        props.perfil.aviso,
);

function guardar() {
    formulario.put(`/proveedor/empresas/${props.empresa.id}/parametros`, {
        preserveScroll: true,
    });
}

const columnasDeUsuarios = [
    { clave: "nombre", titulo: "Nombre" },
    { clave: "correo", titulo: "Correo" },
    { clave: "rol", titulo: "Rol" },
    { clave: "ultimoAcceso", titulo: "Último acceso" },
    { clave: "acciones", titulo: "Acciones" },
];

const { activo: reenviandoInvitacion, ejecutar: ejecutarReenvio } =
    usarAccionPorFila();

function reenviarInvitacion(usuario) {
    ejecutarReenvio(
        usuario.id,
        `/proveedor/empresas/${props.empresa.id}/usuarios/${usuario.id}/invitacion`,
    );
}

const { activo: restableciendoContrasena, ejecutar: ejecutarRestablecimiento } =
    usarAccionPorFila();

function restablecerContrasena(usuario) {
    ejecutarRestablecimiento(
        usuario.id,
        `/proveedor/empresas/${props.empresa.id}/usuarios/${usuario.id}/contrasena`,
    );
}

const columnasDeNovedadesPorMes = [
    { clave: "mes", titulo: "Mes" },
    { clave: "total", titulo: "Novedades registradas", numerica: true },
];

const columnasDeModulos = [
    { clave: "modulo", titulo: "Módulo" },
    { clave: "registros", titulo: "Registros", numerica: true },
    { clave: "ultimo", titulo: "Último registro" },
];

const columnasDeAlertas = [
    { clave: "programada", titulo: "Programada" },
    { clave: "enviada", titulo: "Enviada" },
    { clave: "tipo", titulo: "Alerta" },
    { clave: "empleado", titulo: "Sobre" },
    { clave: "destinatarios", titulo: "Destinatarios" },
    { clave: "estado", titulo: "Estado" },
    { clave: "intentos", titulo: "Intentos", numerica: true },
];

/*
 * Las cuatro acciones de la pestaña «Suscripción». Cada modal lleva su propio
 * `useForm` porque cada uno envía a una dirección distinta, y el que decide qué
 * se ve —suspender o reactivar— es el estado que llegó del servidor, nunca una
 * bandera de la pantalla.
 */
const RUTA_SUSCRIPCION = computed(
    () => `/proveedor/suscripciones/${props.suscripcion.id}`,
);

const modal = ref(null);

const formularioDePago = useForm({ monto: "", fecha: "", numero_factura: "" });
const formularioDePlan = useForm({ plan_id: "", aplica_desde: "" });
const formularioDeMotivo = useForm({ motivo: "" });

function cerrarModal() {
    modal.value = null;
}

function registrarPago() {
    formularioDePago.post(`${RUTA_SUSCRIPCION.value}/pagos`, {
        preserveScroll: true,
        onSuccess: () => {
            formularioDePago.reset();
            cerrarModal();
        },
    });
}

function cambiarDePlan() {
    formularioDePlan.put(`${RUTA_SUSCRIPCION.value}/plan`, {
        preserveScroll: true,
        onSuccess: () => {
            formularioDePlan.reset();
            cerrarModal();
        },
    });
}

function guardarMotivo() {
    const accion = props.suscripcion.suspendida ? "reactivar" : "suspender";

    formularioDeMotivo.post(`${RUTA_SUSCRIPCION.value}/${accion}`, {
        preserveScroll: true,
        onSuccess: () => {
            formularioDeMotivo.reset();
            cerrarModal();
        },
    });
}

// La fila del pago que se está a punto de anular, o nulo. El botón de la
// tabla ya no anula directamente: abre esta confirmación con la fecha nueva
// del cobro, que es la consecuencia que `Suscripciones::consecuenciaDeAnular`
// ya trae calculada en la prop `suscripcion`.
const pagoPorAnular = ref(null);
const anulando = ref(false);

function anularPago() {
    if (pagoPorAnular.value === null) {
        return;
    }

    anulando.value = true;
    router.post(
        `${RUTA_SUSCRIPCION.value}/pagos/${pagoPorAnular.value.id}/anular`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => (pagoPorAnular.value = null),
            onFinish: () => (anulando.value = false),
        },
    );
}

const columnasDelHistorial = [
    { clave: "fecha", titulo: "Fecha" },
    { clave: "monto", titulo: "Monto", numerica: true },
    { clave: "numeroFactura", titulo: "Factura" },
    { clave: "estado", titulo: "Estado" },
    { clave: "acciones", titulo: "" },
];

/*
 * La bitácora interna (4.3) y el acceso al entorno del cliente (4.4).
 *
 * Van juntos en la pantalla porque son la misma pregunta —qué ha pasado con
 * este cliente— pero son lo contrario en visibilidad: la nota es interna del
 * equipo y la visita queda en la auditoría de la empresa, que él sí lee. Los
 * dos textos que lo dicen los escribe el servidor.
 */
const formularioDeNota = useForm({ texto: "" });
const formularioDeAcceso = useForm({ motivo: "", escritura: false });

function guardarNota() {
    formularioDeNota.post(`/proveedor/empresas/${props.empresa.id}/notas`, {
        preserveScroll: true,
        onSuccess: () => formularioDeNota.reset(),
    });
}

// La nota que se está a punto de quitar, o nulo. Igual que con el pago: el
// botón de la lista abre la confirmación en vez de borrar directamente.
const notaPorQuitar = ref(null);
const quitandoNota = ref(false);

function quitarNota() {
    if (notaPorQuitar.value === null) {
        return;
    }

    quitandoNota.value = true;
    router.delete(
        `/proveedor/empresas/${props.empresa.id}/notas/${notaPorQuitar.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => (notaPorQuitar.value = null),
            onFinish: () => (quitandoNota.value = false),
        },
    );
}

function entrarAlEntorno() {
    formularioDeAcceso.post(`/proveedor/empresas/${props.empresa.id}/entorno`);
}

const columnasDeVisitas = [
    { clave: "cuando", titulo: "Cuándo" },
    { clave: "quien", titulo: "Quién" },
    { clave: "motivo", titulo: "Motivo" },
    { clave: "modo", titulo: "Modo" },
    { clave: "duracion", titulo: "Duración" },
];
</script>

<template>
    <Head :title="empresa.nombre" />

    <ArmazonProveedor>
        <Link :href="volver" class="text-apoyo font-medium text-accion">
            ← Volver a empresas
        </Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    {{ empresa.nombre }}
                </h1>
                <p
                    class="mt-1 flex flex-wrap items-center gap-2 text-apoyo text-texto-secundario"
                >
                    <span>NIT {{ empresa.nit }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ empresa.clienteDesde }}</span>
                    <Etiqueta :tono="empresa.estadoTono">
                        {{ empresa.estado }}
                    </Etiqueta>
                </p>
            </div>

            <div class="flex flex-col items-end gap-1">
                <div class="flex flex-wrap gap-2">
                    <!-- Una descarga, no una visita de Inertia: por eso un enlace
                         con el aspecto del botón secundario. -->
                    <a
                        v-if="empresa.exportacion.disponible"
                        :href="empresa.exportacion.url"
                        class="inline-flex h-control items-center justify-center rounded-[var(--radius-caja)] border border-borde bg-superficie px-4 text-tabla font-medium text-texto transition-colors hover:bg-superficie-resaltada"
                    >
                        Exportar toda la información
                    </a>
                    <Boton
                        tipo="button"
                        variante="secundario"
                        @click="modal = 'entorno'"
                    >
                        {{ acceso.boton }}
                    </Boton>
                </div>
                <p
                    v-if="empresa.exportacion.ultima"
                    class="text-apoyo text-texto-secundario"
                >
                    {{ empresa.exportacion.ultima }}
                </p>
            </div>
        </div>

        <dl
            class="mt-4 grid gap-3 rounded-[var(--radius-caja)] border border-borde bg-superficie p-4 sm:grid-cols-3 lg:grid-cols-5"
        >
            <div v-for="cifra in cifras" :key="cifra.etiqueta">
                <dt class="text-apoyo text-texto-secundario">
                    {{ cifra.etiqueta }}
                </dt>
                <dd class="text-cuerpo font-semibold text-texto">
                    {{ cifra.valor }}
                </dd>
            </div>
        </dl>

        <div class="mt-5">
            <Pestanas v-model="activa" :pestanas="pestanas" />

            <div
                :id="`panel-${activa}`"
                role="tabpanel"
                :aria-labelledby="`pestana-${activa}`"
                class="mt-4"
            >
                <div v-if="activa === 'resumen'" class="grid gap-4 lg:grid-cols-3">
                    <ListaDeDatos
                        titulo="Datos de la empresa"
                        :campos="resumen.datos"
                    />
                    <ListaDeDatos titulo="Plan contratado" :campos="resumen.plan" />

                    <section
                        class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
                    >
                        <h3 class="text-seccion font-semibold text-texto">
                            Alta del cliente
                        </h3>

                        <dl class="mt-3">
                            <div
                                v-for="campo in resumen.alta"
                                :key="campo.etiqueta"
                                class="flex items-baseline justify-between gap-4 border-b border-borde py-2"
                            >
                                <dt class="text-apoyo text-texto-secundario">
                                    {{ campo.etiqueta }}
                                </dt>
                                <dd
                                    class="text-right text-cuerpo font-semibold text-texto"
                                >
                                    {{ campo.valor }}
                                </dd>
                            </div>

                            <div
                                class="flex items-baseline justify-between gap-4 py-2"
                            >
                                <dt class="text-apoyo text-texto-secundario">
                                    Certificado de Cámara de Comercio
                                </dt>
                                <dd class="text-right text-cuerpo font-semibold">
                                    <a
                                        v-if="resumen.certificado"
                                        :href="resumen.certificado.url"
                                        class="text-accion hover:underline"
                                    >
                                        {{ resumen.certificado.nombre }}
                                    </a>
                                    <span v-else class="text-texto-secundario">
                                        {{ resumen.sinCertificado }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <form
                    v-else-if="activa === 'parametros'"
                    @submit.prevent="guardar"
                >
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section
                            v-for="parametro in parametros"
                            :key="parametro.clave"
                            class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
                        >
                            <h2 class="text-seccion font-semibold text-texto">
                                {{ parametro.titulo }}
                            </h2>
                            <p class="mt-1 mb-3 text-apoyo text-texto-secundario">
                                {{ parametro.explicacion }}
                            </p>

                            <Selector
                                v-model="formulario[parametro.clave]"
                                :etiqueta="parametro.titulo"
                                :opciones="parametro.opciones"
                                :con-marcador="false"
                                :error="formulario.errors[parametro.clave]"
                            />

                            <Aviso
                                v-if="
                                    parametro.clave === CLAVE_DEL_PERFIL &&
                                    avisoDelPerfil
                                "
                                tono="advertencia"
                                class="mt-3"
                            >
                                {{ perfil.aviso }}
                            </Aviso>
                        </section>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <Boton
                            :enviando="formulario.processing"
                            :deshabilitado="!formulario.isDirty"
                        >
                            Guardar parámetros
                        </Boton>
                        <span class="text-apoyo text-texto-terciario">
                            {{
                                formulario.isDirty
                                    ? "El cambio queda registrado en la auditoría de esta empresa."
                                    : "No hay cambios por guardar."
                            }}
                        </span>
                    </div>
                </form>

                <div v-else-if="activa === 'usuarios'">
                    <TablaDatos
                        :columnas="columnasDeUsuarios"
                        :filas="usuarios"
                        :clicable="false"
                        titulo-vacio="Esta empresa todavía no tiene usuarios"
                        descripcion-vacio="El superadministrador se crea junto con la empresa, así que esto solo pasa con las empresas cargadas a mano."
                    >
                        <template #celda-acciones="{ fila }">
                            <div class="flex flex-wrap gap-2 py-1">
                                <Boton
                                    v-if="fila.invitacionPendiente"
                                    tipo="button"
                                    variante="secundario"
                                    :enviando="reenviandoInvitacion === fila.id"
                                    @click="reenviarInvitacion(fila)"
                                >
                                    Enviar invitación
                                </Boton>
                                <Boton
                                    tipo="button"
                                    variante="texto"
                                    :enviando="restableciendoContrasena === fila.id"
                                    @click="restablecerContrasena(fila)"
                                >
                                    Enviar enlace de contraseña
                                </Boton>
                            </div>
                        </template>
                    </TablaDatos>

                    <p class="mt-3 text-apoyo text-texto-terciario">
                        La contraseña de un usuario no se puede ver desde aquí ni
                        desde ninguna otra parte. Lo que se le envía es un enlace
                        para que la defina él.
                    </p>
                </div>

                <div v-else-if="activa === 'uso'" class="grid gap-4 lg:grid-cols-2">
                    <ListaDeDatos
                        titulo="Cómo usa el sistema"
                        :campos="uso.cifras"
                        class="lg:col-span-2 lg:max-w-xl"
                    />

                    <section>
                        <h3 class="text-seccion font-semibold text-texto">
                            Novedades registradas por mes
                        </h3>
                        <p class="mt-1 mb-3 text-apoyo text-texto-secundario">
                            Incapacidades, vacaciones, licencias, movimientos y
                            terminaciones, por el mes en que se cargaron.
                        </p>
                        <TablaDatos
                            :columnas="columnasDeNovedadesPorMes"
                            :filas="uso.novedadesPorMes"
                            :clicable="false"
                        />
                    </section>

                    <section>
                        <h3 class="text-seccion font-semibold text-texto">
                            Módulos que usa de verdad
                        </h3>
                        <p class="mt-1 mb-3 text-apoyo text-texto-secundario">
                            Un módulo sin registros no le está sirviendo: es la
                            conversación de la renovación.
                        </p>
                        <TablaDatos
                            :columnas="columnasDeModulos"
                            :filas="uso.modulos"
                            :clicable="false"
                        >
                            <template #celda-modulo="{ fila }">
                                <span class="flex items-center gap-2">
                                    <Etiqueta :tono="fila.usa ? 'exito' : 'neutro'">
                                        {{ fila.usa ? "En uso" : "Sin uso" }}
                                    </Etiqueta>
                                    {{ fila.modulo }}
                                </span>
                            </template>
                        </TablaDatos>
                    </section>
                </div>

                <div v-else-if="activa === 'suscripcion'">
                    <div
                        v-if="!suscripcion.existe"
                        class="rounded-[var(--radius-modal)] border border-borde bg-superficie"
                    >
                        <EstadoVacio
                            :titulo="suscripcion.titulo"
                            :descripcion="suscripcion.descripcion"
                        >
                            <Link :href="suscripcion.url">
                                <Boton tipo="button">Ir a Suscripciones</Boton>
                            </Link>
                        </EstadoVacio>
                    </div>

                    <div v-else>
                        <div class="flex flex-wrap items-center gap-2">
                            <Etiqueta :tono="suscripcion.estadoTono">
                                {{ suscripcion.estado }}
                            </Etiqueta>
                            <Etiqueta :tono="suscripcion.cobroTono">
                                {{ suscripcion.cobro }}
                            </Etiqueta>
                        </div>

                        <Aviso
                            v-if="suscripcion.suspension"
                            tono="error"
                            class="mt-3"
                        >
                            Servicio suspendido el
                            {{ suscripcion.suspension.cuando }}:
                            {{ suscripcion.suspension.motivo }}
                        </Aviso>

                        <Aviso
                            v-else-if="suscripcion.reactivacion"
                            tono="informacion"
                            class="mt-3"
                        >
                            Reactivado el {{ suscripcion.reactivacion.cuando }}:
                            {{ suscripcion.reactivacion.motivo }}
                        </Aviso>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1.4fr]">
                            <div>
                                <ListaDeDatos
                                    titulo="Suscripción"
                                    :campos="suscripcion.datos"
                                />

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Boton
                                        tipo="button"
                                        @click="modal = 'pago'"
                                    >
                                        Registrar pago
                                    </Boton>
                                    <Boton
                                        tipo="button"
                                        variante="secundario"
                                        @click="modal = 'plan'"
                                    >
                                        Cambiar de plan
                                    </Boton>
                                    <Boton
                                        tipo="button"
                                        variante="secundario"
                                        @click="modal = 'motivo'"
                                    >
                                        {{
                                            suscripcion.suspendida
                                                ? "Reactivar servicio"
                                                : "Suspender servicio"
                                        }}
                                    </Boton>
                                </div>

                                <p class="mt-3 text-apoyo text-texto-terciario">
                                    {{ suscripcion.aviso }}
                                </p>
                            </div>

                            <div>
                                <h3 class="text-seccion font-semibold text-texto">
                                    Historial de facturas y pagos
                                </h3>

                                <div class="mt-3">
                                    <TablaDatos
                                        :columnas="columnasDelHistorial"
                                        :filas="suscripcion.historial"
                                        :clicable="false"
                                        titulo-vacio="Todavía no se ha registrado ningún pago"
                                        descripcion-vacio="Cada pago que entre queda aquí con su número de factura, y no se borra nunca: si se registró mal, se anula."
                                    >
                                        <template #celda-estado="{ fila }">
                                            <Etiqueta :tono="fila.tono">
                                                {{ fila.estado }}
                                            </Etiqueta>
                                        </template>
                                        <template #celda-acciones="{ fila }">
                                            <Boton
                                                v-if="!fila.anulada"
                                                tipo="button"
                                                variante="texto"
                                                @click="pagoPorAnular = fila"
                                            >
                                                Anular
                                            </Boton>
                                        </template>
                                    </TablaDatos>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="activa === 'bitacora'"
                    class="grid gap-4 lg:grid-cols-2"
                >
                    <section
                        class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
                    >
                        <h3 class="text-seccion font-semibold text-texto">
                            {{ bitacora.titulo }}
                        </h3>
                        <p class="mt-1 text-apoyo text-texto-secundario">
                            {{ bitacora.ayuda }}
                        </p>

                        <EstadoVacio
                            v-if="bitacora.notas.length === 0"
                            :titulo="bitacora.vacia.titulo"
                            :descripcion="bitacora.vacia.descripcion"
                        />

                        <ol v-else class="mt-4">
                            <li
                                v-for="nota in bitacora.notas"
                                :key="nota.id"
                                class="border-l-2 border-borde py-2 pl-3"
                            >
                                <div
                                    class="flex items-baseline justify-between gap-3"
                                >
                                    <p class="text-apoyo text-texto-terciario">
                                        {{ nota.fecha }} · {{ nota.autor }}
                                    </p>
                                    <Boton
                                        tipo="button"
                                        variante="texto"
                                        @click="notaPorQuitar = nota"
                                    >
                                        Quitar
                                    </Boton>
                                </div>
                                <p class="text-cuerpo text-texto">
                                    {{ nota.texto }}
                                </p>
                            </li>
                        </ol>

                        <form class="mt-4" @submit.prevent="guardarNota">
                            <CampoTexto
                                v-model="formularioDeNota.texto"
                                etiqueta="Nueva nota"
                                :ayuda="bitacora.ayudaDeLaNota"
                                :error="formularioDeNota.errors.texto"
                            />

                            <div class="mt-3">
                                <Boton
                                    :enviando="formularioDeNota.processing"
                                    :deshabilitado="!formularioDeNota.texto"
                                >
                                    Guardar nota
                                </Boton>
                            </div>
                        </form>
                    </section>

                    <section>
                        <h3 class="text-seccion font-semibold text-texto">
                            {{ bitacora.visitas.titulo }}
                        </h3>
                        <p class="mt-1 mb-3 text-apoyo text-texto-secundario">
                            {{ bitacora.visitas.ayuda }}
                        </p>

                        <TablaDatos
                            :columnas="columnasDeVisitas"
                            :filas="bitacora.visitas.filas"
                            :clicable="false"
                            :titulo-vacio="bitacora.visitas.vacio"
                            :descripcion-vacio="acceso.condiciones[0]"
                        >
                            <template #celda-modo="{ fila }">
                                <Etiqueta :tono="fila.tono">
                                    {{ fila.modo }}
                                </Etiqueta>
                            </template>
                        </TablaDatos>
                    </section>
                </div>

                <div v-else-if="activa === 'alertas'">
                    <p class="mb-3 text-apoyo text-texto-secundario">
                        Todo lo que el motor de avisos le ha enviado a esta
                        empresa, con su resultado. Las últimas primero.
                    </p>

                    <TablaDatos
                        :columnas="columnasDeAlertas"
                        :filas="alertas.filas"
                        :clicable="false"
                        :titulo-vacio="alertas.vacio.titulo"
                        :descripcion-vacio="alertas.vacio.descripcion"
                    >
                        <template #celda-destinatarios="{ fila }">
                            {{ fila.destinatarios.join(", ") }}
                        </template>
                        <template #celda-estado="{ fila }">
                            <Etiqueta :tono="fila.tono">{{ fila.estado }}</Etiqueta>
                        </template>
                    </TablaDatos>

                    <p
                        v-if="alertas.hayMas"
                        class="mt-3 text-apoyo text-texto-terciario"
                    >
                        Se muestran las {{ alertas.maximo }} más recientes de
                        {{ alertas.total }}.
                        <Link
                            :href="alertas.consolaUrl"
                            class="font-medium text-accion"
                        >
                            Ver todas en la consola de alertas
                        </Link>
                    </p>
                </div>
            </div>
        </div>

        <Modal
            :abierto="modal === 'entorno'"
            :titulo="acceso.titulo"
            @cerrar="cerrarModal"
        >
            <p class="text-apoyo text-texto-secundario">
                Vas a entrar al sistema de <b>{{ empresa.nombre }}</b> con tu
                propia cuenta, no con la de nadie de la empresa.
            </p>

            <Aviso
                tono="advertencia"
                :titulo="acceso.tituloCondiciones"
                class="mt-3"
            >
                <ul>
                    <li
                        v-for="condicion in acceso.condiciones"
                        :key="condicion"
                    >
                        · {{ condicion }}
                    </li>
                </ul>
            </Aviso>

            <div class="mt-4">
                <CampoTexto
                    v-model="formularioDeAcceso.motivo"
                    etiqueta="Motivo del acceso"
                    :ayuda="acceso.ayudaDelMotivo"
                    :error="formularioDeAcceso.errors.motivo"
                />
            </div>

            <label
                class="mt-4 flex items-start gap-2 text-apoyo text-texto-secundario"
            >
                <input
                    v-model="formularioDeAcceso.escritura"
                    type="checkbox"
                    class="mt-1"
                />
                <span>{{ acceso.etiquetaDeEscritura }}</span>
            </label>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="cerrarModal">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="formularioDeAcceso.processing"
                    @click="entrarAlEntorno"
                >
                    Entrar
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="modal === 'pago'"
            titulo="Registrar un pago"
            @cerrar="cerrarModal"
        >
            <div class="grid gap-4">
                <CampoDinero
                    v-model="formularioDePago.monto"
                    etiqueta="Monto recibido"
                    ayuda="Se escribe y no se deduce del plan: hay clientes que pagan dos meses juntos."
                    :error="formularioDePago.errors.monto"
                />
                <CampoFecha
                    v-model="formularioDePago.fecha"
                    etiqueta="Fecha del pago"
                    :error="formularioDePago.errors.fecha"
                />
                <CampoTexto
                    v-model="formularioDePago.numero_factura"
                    etiqueta="Número de factura"
                    opcional
                    ayuda="Opcional mientras la facturación electrónica no esté habilitada."
                    :error="formularioDePago.errors.numero_factura"
                />
            </div>

            <p class="mt-4 text-apoyo text-texto-secundario">
                Al guardarlo, el próximo cobro se corre un mes desde la fecha que
                tiene hoy. Pagar tarde no corre el calendario del cliente.
            </p>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="cerrarModal">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="formularioDePago.processing"
                    @click="registrarPago"
                >
                    Registrar pago
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="modal === 'plan'"
            titulo="Cambiar de plan"
            @cerrar="cerrarModal"
        >
            <div class="grid gap-4">
                <Selector
                    v-model="formularioDePlan.plan_id"
                    etiqueta="Plan nuevo"
                    :opciones="suscripcion.planes"
                    :error="formularioDePlan.errors.plan_id"
                />
                <CampoFecha
                    v-model="formularioDePlan.aplica_desde"
                    etiqueta="Aplica desde"
                    ayuda="Puede ser una fecha futura: se acuerda hoy y arranca el mes entrante."
                    :error="formularioDePlan.errors.aplica_desde"
                />
            </div>

            <p class="mt-4 text-apoyo text-texto-secundario">
                El plan queda registrado con su fecha y el cambio se ve en la
                auditoría de esta empresa. Los empleados y los usuarios incluidos
                pasan a ser los del plan nuevo.
            </p>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="cerrarModal">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="formularioDePlan.processing"
                    @click="cambiarDePlan"
                >
                    Cambiar de plan
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="modal === 'motivo'"
            :titulo="
                suscripcion.suspendida
                    ? 'Reactivar el servicio'
                    : 'Suspender el servicio'
            "
            @cerrar="cerrarModal"
        >
            <p class="text-apoyo text-texto-secundario">
                {{
                    suscripcion.suspendida
                        ? "Sus usuarios vuelven a entrar en la petición siguiente y encuentran todo donde lo dejaron."
                        : suscripcion.aviso
                }}
            </p>

            <div class="mt-4">
                <CampoTexto
                    v-model="formularioDeMotivo.motivo"
                    etiqueta="Motivo"
                    ayuda="Queda como constancia en la auditoría de esta empresa, y el cliente la puede ver."
                    :error="formularioDeMotivo.errors.motivo"
                />
            </div>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="cerrarModal">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="formularioDeMotivo.processing"
                    @click="guardarMotivo"
                >
                    {{ suscripcion.suspendida ? "Reactivar" : "Suspender" }}
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="pagoPorAnular !== null"
            titulo="Anular el pago"
            @cerrar="pagoPorAnular = null"
        >
            <p class="text-cuerpo text-texto">
                Vas a anular el pago de
                <strong>{{ pagoPorAnular?.monto }}</strong> del
                {{ pagoPorAnular?.fecha }} ({{ pagoPorAnular?.numeroFactura }}).
                La factura no se borra: queda marcada como anulada y sigue en
                el historial. {{ suscripcion.consecuenciaDeAnular }}
            </p>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="pagoPorAnular = null">
                    Cancelar
                </Boton>
                <Boton
                    variante="destructivo"
                    tipo="button"
                    :enviando="anulando"
                    @click="anularPago"
                >
                    Anular el pago
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="notaPorQuitar !== null"
            titulo="Quitar la nota"
            @cerrar="notaPorQuitar = null"
        >
            <p class="text-cuerpo text-texto">
                Vas a quitar la nota del {{ notaPorQuitar?.fecha }}:
                «{{ notaPorQuitar?.texto }}». Deja de verse en la bitácora
                interna del equipo; el cliente nunca la vio.
            </p>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="notaPorQuitar = null">
                    Cancelar
                </Boton>
                <Boton
                    variante="destructivo"
                    tipo="button"
                    :enviando="quitandoNota"
                    @click="quitarNota"
                >
                    Quitar la nota
                </Boton>
            </template>
        </Modal>
    </ArmazonProveedor>
</template>
