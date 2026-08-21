<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Notificacion from "@/Components/Notificacion.vue";
import Pestanas from "@/Components/Pestanas.vue";
import Selector from "@/Components/Selector.vue";

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
});

const pestanas = [
    { clave: "parametros", titulo: "Parámetros" },
    { clave: "empresa", titulo: "Datos de la empresa" },
    { clave: "usuarios", titulo: "Usuarios y permisos" },
    { clave: "facturacion", titulo: "Facturación" },
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

                <div
                    v-else
                    class="rounded-[var(--radius-modal)] border border-borde bg-superficie"
                >
                    <EstadoVacio
                        titulo="Esta pestaña todavía no tiene contenido"
                        descripcion="Los usuarios y sus permisos, y la facturación, llegan en su propia tarea. Los parámetros de la empresa ya funcionan."
                    />
                </div>
            </div>
        </div>
    </Armazon>
</template>
