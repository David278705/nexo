<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import PasosDelAsistente from "@/Components/PasosDelAsistente.vue";
import Selector from "@/Components/Selector.vue";
import SelectorUbicacion from "@/Components/SelectorUbicacion.vue";

/*
 * Crear una empresa cliente: los tres pasos del documento 4, sección 4.2, y de
 * `prototipos/panel-proveedor.html`.
 *
 * Se diligencia al final de la reunión de onboarding, delante del cliente, y por
 * eso los pasos son los del documento y en su orden: primero los datos que ya se
 * traían, después los parámetros —que se deciden en vivo, hablando— y por último
 * el plan y la cuenta de quien va a entrar.
 *
 * Nada se guarda hasta el último paso, igual que en el registro de empleado: es
 * una sola escritura y una sola transacción en el servidor, así que retroceder
 * no cuesta nada.
 *
 * Las explicaciones de los tres parámetros no están escritas aquí: llegan del
 * servidor, de `ParametrosDeLaEmpresa`, que es el mismo sitio del que las lee la
 * pantalla de configuración del cliente. Lo que se le lee al cliente en la
 * reunión y lo que él encuentra después en su propia pantalla tienen que ser las
 * mismas palabras.
 */
const props = defineProps({
    // Los títulos de los tres pasos, con las palabras del documento.
    pasos: { type: Array, required: true },
    // Los tres parámetros con su título, su explicación y sus dos opciones.
    parametros: { type: Array, required: true },
    ubicaciones: { type: Object, required: true },
    // Qué admite el adjunto del certificado. Lo decide el servidor, que es quien
    // lo valida: si la pantalla lo dijera por su cuenta, podrían separarse.
    certificado: { type: Object, required: true },
    volver: { type: String, required: true },
});

const form = useForm({
    nombre: "",
    nit: "",
    telefono: "",
    ciudad_id: "",
    certificado: null,

    // Los tres parámetros arrancan sin responder a propósito. Un valor por
    // defecto en «¿el sábado es día laboral?» es una respuesta que nadie dio y
    // que después nadie recuerda haber dado.
    ...Object.fromEntries(props.parametros.map((uno) => [uno.clave, ""])),

    plan_nombre: "",
    plan_empleados_incluidos: "",
    plan_usuarios_incluidos: "",
    plan_inicia_el: "",

    superadministrador: { nombre: "", correo: "" },
});

/*
 * La sede de la empresa. El componente de ubicación trabaja con
 * `{ pais, departamento, ciudad }` y aquí solo se guarda el municipio: la
 * empresa cliente es colombiana —el sistema solo gestiona contratos laborales
 * colombianos—, así que el país no se pregunta y el departamento solo sirve para
 * encadenar la lista.
 */
const sede = ref({
    pais: props.ubicaciones.codigoDeColombia,
    departamento: "",
    ciudad: "",
});

function cambiarSede(valor) {
    sede.value = valor;
    form.ciudad_id = valor.ciudad;
}

const nombreDelArchivo = computed(() => form.certificado?.name ?? "");

function adjuntar(evento) {
    form.certificado = evento.target.files?.[0] ?? null;
}

const formatos = computed(() =>
    props.certificado.formatos.map((uno) => uno.toUpperCase()).join(", "),
);

const megasMaximos = computed(() =>
    Math.round(props.certificado.tamanoMaximoEnKb / 1024),
);

/*
 * Qué campo obligatorio vive en qué paso. Sirve para las dos direcciones: no
 * dejar avanzar con uno vacío, y saber a qué paso volver cuando el servidor
 * devuelve un error. El certificado no está: es opcional.
 */
const campos = computed(() => [
    ["nombre", "nit", "telefono", "ciudad_id"],
    props.parametros.map((uno) => uno.clave),
    [
        "plan_nombre",
        "plan_empleados_incluidos",
        "plan_usuarios_incluidos",
        "plan_inicia_el",
        "superadministrador.nombre",
        "superadministrador.correo",
    ],
]);

const paso = ref(0);

const esElUltimo = computed(() => paso.value === props.pasos.length - 1);

function valorDe(campo) {
    return campo.split(".").reduce((dato, parte) => (dato ?? {})[parte], form);
}

function continuar() {
    const faltantes = campos.value[paso.value].filter(
        (campo) => String(valorDe(campo) ?? "").trim() === "",
    );

    faltantes.forEach((campo) =>
        form.setError(campo, "Este dato es obligatorio."),
    );

    if (faltantes.length > 0) {
        return;
    }

    paso.value += 1;
}

function volverAlPaso(indice) {
    if (indice <= paso.value) {
        paso.value = indice;
    }
}

function guardar() {
    form.post("/proveedor/empresas", {
        onError: (errores) => {
            const primero = campos.value.findIndex((delPaso) =>
                delPaso.some((campo) => campo in errores),
            );

            paso.value = primero === -1 ? paso.value : primero;
        },
    });
}
</script>

<template>
    <Head title="Crear empresa" />

    <ArmazonProveedor>
        <Link :href="volver" class="text-apoyo text-accion">
            ← Cancelar y volver
        </Link>

        <h1 class="mt-2 text-titulo font-semibold text-texto">Crear empresa</h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            Se diligencia al final de la reunión de onboarding, delante del
            cliente · Paso {{ paso + 1 }} de {{ pasos.length }}
        </p>

        <PasosDelAsistente
            :titulos="pasos"
            :actual="paso"
            @ir="volverAlPaso"
        />

        <form
            class="mt-5"
            @submit.prevent="esElUltimo ? guardar() : continuar()"
        >
            <!-- Paso 1 · Datos de la empresa -->
            <div
                v-show="paso === 0"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Datos de la empresa
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <CampoTexto
                        v-model="form.nombre"
                        etiqueta="Razón social"
                        :error="form.errors.nombre ?? ''"
                    />

                    <CampoTexto
                        v-model="form.nit"
                        etiqueta="NIT"
                        ayuda="Con el dígito de verificación."
                        :error="form.errors.nit ?? ''"
                    />

                    <CampoTexto
                        v-model="form.telefono"
                        etiqueta="Teléfono"
                        :error="form.errors.telefono ?? ''"
                    />
                </div>

                <div class="mt-4">
                    <SelectorUbicacion
                        :model-value="sede"
                        solo-colombia
                        :paises="ubicaciones.paises"
                        :departamentos="ubicaciones.departamentos"
                        :codigo-de-colombia="ubicaciones.codigoDeColombia"
                        :errores="{ ciudad: form.errors.ciudad_id ?? '' }"
                        @update:model-value="cambiarSede"
                    />
                </div>

                <div class="mt-4">
                    <label
                        for="certificado"
                        class="block text-apoyo font-medium text-texto"
                    >
                        Certificado de Cámara de Comercio
                        <span class="font-normal text-texto-terciario">
                            (opcional)
                        </span>
                    </label>

                    <input
                        id="certificado"
                        type="file"
                        class="mt-1 block w-full rounded-[var(--radius-caja)] border border-borde bg-superficie p-2 text-tabla text-texto"
                        :accept="
                            certificado.formatos
                                .map((uno) => `.${uno}`)
                                .join(',')
                        "
                        @change="adjuntar"
                    />

                    <p class="mt-1 text-apoyo text-texto-secundario">
                        Se solicita antes de firmar: confirma que quien firma
                        puede obligar a la empresa. {{ formatos }}, hasta
                        {{ megasMaximos }} MB. Si todavía no está, la empresa se
                        crea igual y queda como pendiente en el resumen.
                    </p>

                    <p
                        v-if="nombreDelArchivo"
                        class="mt-1 text-apoyo text-texto"
                    >
                        Adjunto: {{ nombreDelArchivo }}
                    </p>

                    <p
                        v-if="form.errors.certificado"
                        class="mt-1 text-apoyo text-error"
                    >
                        {{ form.errors.certificado }}
                    </p>
                </div>
            </div>

            <!-- Paso 2 · Parámetros -->
            <div
                v-show="paso === 1"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Parámetros
                </h2>

                <Aviso class="mt-3">
                    Se preguntan y se deciden en vivo con el cliente. Son los
                    mismos tres que él va a ver después en su pantalla de
                    configuración, y los puede cambiar cuando quiera.
                </Aviso>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <Selector
                        v-for="parametro in parametros"
                        :key="parametro.clave"
                        v-model="form[parametro.clave]"
                        :etiqueta="parametro.titulo"
                        :opciones="parametro.opciones"
                        :ayuda="parametro.explicacion"
                        :error="form.errors[parametro.clave] ?? ''"
                    />
                </div>
            </div>

            <!-- Paso 3 · Plan y cuenta -->
            <div
                v-show="paso === 2"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Plan y cuenta del superadministrador
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <CampoTexto
                        v-model="form.plan_nombre"
                        etiqueta="Plan"
                        ayuda="El nivel que se le nombró al cliente. Ej.: «Pequeña — hasta 50 empleados»."
                        :error="form.errors.plan_nombre ?? ''"
                    />

                    <CampoTexto
                        v-model="form.plan_empleados_incluidos"
                        etiqueta="Empleados incluidos"
                        tipo="number"
                        :error="form.errors.plan_empleados_incluidos ?? ''"
                    />

                    <CampoTexto
                        v-model="form.plan_usuarios_incluidos"
                        etiqueta="Usuarios administradores"
                        tipo="number"
                        :error="form.errors.plan_usuarios_incluidos ?? ''"
                    />

                    <CampoFecha
                        v-model="form.plan_inicia_el"
                        etiqueta="Fecha de inicio"
                        :error="form.errors.plan_inicia_el ?? ''"
                    />
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <CampoTexto
                        v-model="form.superadministrador.nombre"
                        etiqueta="Nombre del superadministrador"
                        :error="form.errors['superadministrador.nombre'] ?? ''"
                    />

                    <CampoTexto
                        v-model="form.superadministrador.correo"
                        etiqueta="Correo"
                        tipo="email"
                        ayuda="Recibe la invitación para definir su contraseña. Nadie más la conoce, ni el proveedor."
                        :error="form.errors['superadministrador.correo'] ?? ''"
                    />
                </div>

                <Aviso class="mt-4">
                    Al crear la empresa se muestra un
                    <strong>resumen de la configuración</strong> para leérselo
                    al cliente en voz alta antes de cerrar la reunión. Evita la
                    discusión posterior de «yo nunca dije que el sábado fuera
                    laboral».
                </Aviso>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <Link v-if="paso === 0" :href="volver">
                    <Boton tipo="button" variante="texto">Cancelar</Boton>
                </Link>

                <Boton
                    v-else
                    tipo="button"
                    variante="secundario"
                    @click="volverAlPaso(paso - 1)"
                >
                    ← Volver
                </Boton>

                <Boton v-if="!esElUltimo" tipo="submit">Continuar →</Boton>

                <Boton v-else tipo="submit" :enviando="form.processing">
                    Crear empresa
                </Boton>
            </div>
        </form>
    </ArmazonProveedor>
</template>
