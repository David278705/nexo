<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Modal from "@/Components/Modal.vue";
import ModalDeBorrado from "@/Components/ModalDeBorrado.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Pestanas from "@/Components/Pestanas.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * Configuración → Parámetros (documento 1, capítulo 4, y documento 3, sección
 * 5.7).
 *
 * Los cuatro parámetros con su explicación en lenguaje claro. Ni los títulos ni
 * las explicaciones ni los textos de las opciones se escriben aquí: llegan del
 * servidor, en `ParametrosDeLaEmpresa`, donde vive también el efecto de cada
 * uno. Esta pantalla los pinta y manda de vuelta `si` o `no`.
 *
 * Los tres parámetros de cálculo son un solo formulario y un solo botón, no tres
 * interruptores que guardan solos: cambiar cómo se cuentan las vacaciones de
 * toda la empresa se confirma a propósito, y así queda una sola línea de
 * auditoría con lo que cambió.
 *
 * El cuarto —los correos que reciben las alertas— es de solo lectura: sus
 * opciones son «los administradores con acceso a cada módulo», así que se cambia
 * en Usuarios y permisos y no aquí.
 */
const props = defineProps({
    empresa: { type: Object, required: true },
    parametros: { type: Array, required: true },
    destinatarios: { type: Object, required: true },
    perfil: { type: Object, required: true },
    usuarios: { type: Array, default: () => [] },
    modulosAsignables: { type: Array, default: () => [] },
    facturacion: { type: Object, required: true },
    legal: { type: Object, required: true },
});

const pestanas = [
    { clave: "parametros", titulo: "Parámetros" },
    { clave: "empresa", titulo: "Datos de la empresa" },
    { clave: "usuarios", titulo: "Usuarios y permisos" },
    { clave: "facturacion", titulo: "Facturación" },
    { clave: "legal", titulo: "Documentos legales" },
];

const activa = ref("parametros");

const CLAVE_DEL_PERFIL = "perfil_sociodemografico_habilitado";

const formulario = useForm(
    Object.fromEntries(
        props.parametros.map((parametro) => [parametro.clave, parametro.valor]),
    ),
);

/*
 * El aviso del capítulo 4 aparece al **activar** el bloque, no cuando ya estaba
 * activo: a quien lo tiene encendido desde el principio no hay nada que
 * advertirle. El texto lo escribe el servidor, que es el único que sabe cuántos
 * empleados hay ya registrados.
 */
const avisoDelPerfil = computed(
    () =>
        formulario[CLAVE_DEL_PERFIL] === "si" &&
        !props.perfil.habilitado &&
        props.perfil.aviso,
);

const confirmacion = computed(() => usePage().props.estado ?? "");

function guardar() {
    formulario.put("/configuracion/parametros", { preserveScroll: true });
}

/*
 * La pestaña «Usuarios y permisos» (documento 3, 5.7, REV-10): invitar a un
 * administrador, editar sus módulos y desactivarlo. El superadministrador no
 * tiene ninguna de las tres acciones —lo dice `fila.esSuperadministrador`— y
 * la pantalla no le ofrece ninguna.
 */
const columnasDeUsuarios = [
    { clave: "nombre", titulo: "Nombre" },
    { clave: "correo", titulo: "Correo" },
    { clave: "rol", titulo: "Rol" },
    { clave: "modulos", titulo: "Módulos" },
    { clave: "acciones", titulo: "Acciones" },
];

const modalNuevoUsuarioAbierto = ref(false);
const formularioUsuario = useForm({ nombre: "", correo: "", modulos: [] });

function abrirNuevoUsuario() {
    formularioUsuario.reset();
    formularioUsuario.clearErrors();
    modalNuevoUsuarioAbierto.value = true;
}

function crearUsuario() {
    formularioUsuario.post("/configuracion/usuarios", {
        preserveScroll: true,
        onSuccess: () => {
            modalNuevoUsuarioAbierto.value = false;
        },
    });
}

const usuarioEnEdicion = ref(null);
const formularioModulos = useForm({ modulos: [] });

function abrirEdicionDeModulos(usuario) {
    usuarioEnEdicion.value = usuario;
    formularioModulos.clearErrors();
    formularioModulos.modulos = [...usuario.modulosAsignados];
}

function guardarModulos() {
    formularioModulos.put(`/configuracion/usuarios/${usuarioEnEdicion.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            usuarioEnEdicion.value = null;
        },
    });
}

const usuarioADesactivar = ref(null);
const formularioDesactivar = useForm({});

/*
 * La pestaña «Facturación» (documento 3, 5.7, REV-11): la misma información
 * que ya calcula y ya prueba `App\Proveedor\PanelDeSuscripciones` del lado
 * del proveedor, de solo lectura. Sin acciones: registrar un pago, cambiar
 * de plan, suspender y reactivar son decisiones del proveedor sobre su
 * cliente, no de esta pantalla.
 */
const columnasDePagos = [
    { clave: "fecha", titulo: "Fecha" },
    { clave: "numeroFactura", titulo: "Factura" },
    { clave: "monto", titulo: "Monto", numerica: true },
    { clave: "estado", titulo: "Estado" },
];

function confirmarDesactivar() {
    formularioDesactivar.delete(`/configuracion/usuarios/${usuarioADesactivar.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            usuarioADesactivar.value = null;
        },
    });
}
</script>

<template>
    <Head title="Configuración" />

    <Armazon>
        <Notificacion :mensaje="confirmacion" />

        <h1 class="text-titulo font-semibold text-texto">Configuración</h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            Parámetros de la empresa y gestión de usuarios
        </p>

        <div class="mt-5">
            <Pestanas v-model="activa" :pestanas="pestanas" />

            <div
                :id="`panel-${activa}`"
                role="tabpanel"
                :aria-labelledby="`pestana-${activa}`"
                class="mt-4"
            >
                <form v-if="activa === 'parametros'" @submit.prevent="guardar">
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
                                    parametro.clave ===
                                        'perfil_sociodemografico_habilitado' &&
                                    avisoDelPerfil
                                "
                                tono="advertencia"
                                class="mt-3"
                            >
                                {{ perfil.aviso }}
                            </Aviso>
                        </section>

                        <ListaDeDatos
                            :titulo="destinatarios.titulo"
                            :campos="destinatarios.filas"
                            vacio="Todavía no hay usuarios a quienes enviar las alertas."
                        />
                    </div>

                    <p class="mt-2 text-apoyo text-texto-terciario">
                        {{ destinatarios.explicacion }}
                    </p>

                    <div class="mt-5 flex items-center gap-3">
                        <Boton
                            :enviando="formulario.processing"
                            :deshabilitado="!formulario.isDirty"
                        >
                            Guardar parámetros
                        </Boton>
                        <span
                            v-if="!formulario.isDirty"
                            class="text-apoyo text-texto-terciario"
                        >
                            No hay cambios por guardar.
                        </span>
                    </div>
                </form>

                <div
                    v-else-if="activa === 'empresa'"
                    class="grid gap-2 lg:max-w-xl"
                >
                    <ListaDeDatos
                        titulo="Datos de la empresa"
                        :campos="[
                            { etiqueta: 'Nombre', valor: empresa.nombre },
                            { etiqueta: 'NIT', valor: empresa.nit },
                        ]"
                    />
                    <p class="text-apoyo text-texto-terciario">
                        {{ empresa.nota }}
                    </p>
                </div>

                <div v-else-if="activa === 'usuarios'">
                    <div class="mb-3 flex justify-end">
                        <Boton tipo="button" @click="abrirNuevoUsuario">
                            Nuevo usuario
                        </Boton>
                    </div>

                    <TablaDatos
                        :columnas="columnasDeUsuarios"
                        :filas="usuarios"
                        :clicable="false"
                        titulo-vacio="Todavía no hay usuarios"
                        descripcion-vacio="Invita al primer administrador para que pueda entrar con sus propios módulos."
                    >
                        <template #celda-acciones="{ fila }">
                            <div
                                v-if="!fila.esSuperadministrador"
                                class="flex flex-wrap gap-2 py-1"
                            >
                                <Boton
                                    tipo="button"
                                    variante="secundario"
                                    @click="abrirEdicionDeModulos(fila)"
                                >
                                    Editar módulos
                                </Boton>
                                <Boton
                                    tipo="button"
                                    variante="texto"
                                    @click="usuarioADesactivar = fila"
                                >
                                    Desactivar
                                </Boton>
                            </div>
                            <span
                                v-else
                                class="text-apoyo text-texto-terciario"
                            >
                                Acceso total, no editable
                            </span>
                        </template>
                    </TablaDatos>

                    <p
                        v-if="usuarios.some((usuario) => usuario.invitacionPendiente)"
                        class="mt-3 text-apoyo text-texto-terciario"
                    >
                        Quien todavía no aceptó su invitación no puede iniciar
                        sesión hasta que defina su contraseña con el enlace
                        que le llegó al correo.
                    </p>

                    <Modal
                        :abierto="modalNuevoUsuarioAbierto"
                        titulo="Nuevo usuario"
                        @cerrar="modalNuevoUsuarioAbierto = false"
                    >
                        <div class="grid gap-4">
                            <CampoTexto
                                v-model="formularioUsuario.nombre"
                                etiqueta="Nombre"
                                :error="formularioUsuario.errors.nombre"
                            />
                            <CampoTexto
                                v-model="formularioUsuario.correo"
                                etiqueta="Correo"
                                tipo="email"
                                ayuda="Recibe la invitación para definir su contraseña. Nadie más la conoce."
                                :error="formularioUsuario.errors.correo"
                            />

                            <fieldset>
                                <legend
                                    class="text-apoyo font-medium text-texto-secundario"
                                >
                                    Módulos
                                </legend>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="modulo in modulosAsignables"
                                        :key="modulo.valor"
                                        class="flex items-center gap-2 text-tabla text-texto"
                                    >
                                        <input
                                            v-model="formularioUsuario.modulos"
                                            type="checkbox"
                                            :value="modulo.valor"
                                            class="h-4 w-4 rounded border-borde"
                                        />
                                        {{ modulo.texto }}
                                    </label>
                                </div>
                            </fieldset>
                        </div>

                        <template #pie>
                            <Boton
                                variante="texto"
                                tipo="button"
                                @click="modalNuevoUsuarioAbierto = false"
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                tipo="button"
                                :enviando="formularioUsuario.processing"
                                @click="crearUsuario"
                            >
                                Invitar
                            </Boton>
                        </template>
                    </Modal>

                    <Modal
                        :abierto="usuarioEnEdicion !== null"
                        titulo="Editar módulos"
                        @cerrar="usuarioEnEdicion = null"
                    >
                        <div v-if="usuarioEnEdicion">
                            <p class="mb-3 text-cuerpo text-texto">
                                {{ usuarioEnEdicion.nombre }}
                            </p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="modulo in modulosAsignables"
                                    :key="modulo.valor"
                                    class="flex items-center gap-2 text-tabla text-texto"
                                >
                                    <input
                                        v-model="formularioModulos.modulos"
                                        type="checkbox"
                                        :value="modulo.valor"
                                        class="h-4 w-4 rounded border-borde"
                                    />
                                    {{ modulo.texto }}
                                </label>
                            </div>
                        </div>

                        <template #pie>
                            <Boton
                                variante="texto"
                                tipo="button"
                                @click="usuarioEnEdicion = null"
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                tipo="button"
                                :enviando="formularioModulos.processing"
                                @click="guardarModulos"
                            >
                                Guardar módulos
                            </Boton>
                        </template>
                    </Modal>

                    <ModalDeBorrado
                        :abierto="usuarioADesactivar !== null"
                        titulo="Desactivar usuario"
                        etiqueta-boton="Desactivar"
                        :mensaje="usuarioADesactivar?.consecuenciaDeDesactivar ?? ''"
                        :enviando="formularioDesactivar.processing"
                        @cerrar="usuarioADesactivar = null"
                        @confirmar="confirmarDesactivar"
                    />
                </div>

                <div v-else-if="activa === 'facturacion'">
                    <div
                        v-if="!facturacion.existe"
                        class="rounded-[var(--radius-modal)] border border-borde bg-superficie"
                    >
                        <EstadoVacio
                            :titulo="facturacion.titulo"
                            :descripcion="facturacion.descripcion"
                        />
                    </div>

                    <div v-else class="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
                        <div>
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <h2 class="text-seccion font-semibold text-texto">
                                    {{ facturacion.plan }}
                                </h2>
                                <Etiqueta :tono="facturacion.estadoTono">
                                    {{ facturacion.estado }}
                                </Etiqueta>
                            </div>

                            <ListaDeDatos :campos="facturacion.datos" />
                        </div>

                        <div>
                            <h3 class="text-seccion font-semibold text-texto">
                                Historial de pagos
                            </h3>

                            <div class="mt-3">
                                <TablaDatos
                                    :columnas="columnasDePagos"
                                    :filas="facturacion.historial"
                                    :clicable="false"
                                    titulo-vacio="Todavía no se ha registrado ningún pago"
                                >
                                    <template #celda-estado="{ fila }">
                                        <Etiqueta :tono="fila.tono">
                                            {{ fila.estado }}
                                        </Etiqueta>
                                    </template>
                                </TablaDatos>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="activa === 'legal'" class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h2 class="text-seccion font-semibold text-texto">
                            Lo que la empresa aceptó
                        </h2>
                        <ul class="mt-3 space-y-3">
                            <li
                                v-for="documento in legal.documentos"
                                :key="documento.url"
                                class="text-sm"
                            >
                                <a
                                    :href="documento.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-accion hover:underline"
                                >
                                    {{ documento.titulo }}
                                </a>
                                <span class="text-texto-terciario">
                                    · versión {{ documento.version }}
                                </span>
                                <p
                                    v-if="documento.aceptado"
                                    class="mt-0.5 text-apoyo text-texto-secundario"
                                >
                                    Aceptado el {{ documento.aceptadoEn }} por
                                    {{ documento.aceptadoPor }}.
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-seccion font-semibold text-texto">
                            Para usar con tus empleados
                        </h2>
                        <p class="mt-1 text-apoyo text-texto-secundario">
                            Como responsable del tratamiento, tu empresa le pide a cada
                            empleado su autorización, incluida la expresa para los datos
                            de salud.
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="plantilla in legal.plantillas"
                                :key="plantilla.url"
                                class="text-sm"
                            >
                                <a
                                    :href="plantilla.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-accion hover:underline"
                                >
                                    {{ plantilla.titulo }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </Armazon>
</template>
