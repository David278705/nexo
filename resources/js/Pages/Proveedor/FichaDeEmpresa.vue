<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Pestanas from "@/Components/Pestanas.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * La ficha de una empresa cliente (documento 4, 4.3).
 *
 * Siete pestañas: cuatro con contenido y tres que dicen de qué tarea depende lo
 * suyo. El texto de esas tres lo escribe el servidor, en `FichaDeEmpresa`, para
 * que no haya que buscar en dos sitios qué falta.
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
    pendientes: { type: Object, required: true },
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

function reenviarInvitacion(usuario) {
    router.post(
        `/proveedor/empresas/${props.empresa.id}/usuarios/${usuario.id}/invitacion`,
        {},
        { preserveScroll: true },
    );
}

function restablecerContrasena(usuario) {
    router.post(
        `/proveedor/empresas/${props.empresa.id}/usuarios/${usuario.id}/contrasena`,
        {},
        { preserveScroll: true },
    );
}

const pendiente = computed(() => props.pendientes[activa.value] ?? null);
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
                                    @click="reenviarInvitacion(fila)"
                                >
                                    Reenviar invitación
                                </Boton>
                                <Boton
                                    tipo="button"
                                    variante="texto"
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

                <div v-else-if="activa === 'uso'">
                    <ListaDeDatos
                        titulo="Cómo usa el sistema"
                        :campos="uso.cifras"
                        class="lg:max-w-xl"
                    />

                    <p class="mt-3 text-apoyo text-texto-terciario">
                        {{ uso.incompleto }}
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-[var(--radius-modal)] border border-borde bg-superficie"
                >
                    <EstadoVacio
                        :titulo="pendiente?.titulo ?? ''"
                        :descripcion="pendiente?.descripcion ?? ''"
                    />
                </div>
            </div>
        </div>
    </ArmazonProveedor>
</template>
