<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import PasosDelAsistente from "@/Components/PasosDelAsistente.vue";
import Selector from "@/Components/Selector.vue";
import SelectorUbicacion from "@/Components/SelectorUbicacion.vue";

/*
 * El registro de empleado: el asistente de cuatro pasos del documento 1,
 * secciones 5.1 a 5.3, y del documento 3, sección 5.3.
 *
 * Los pasos existen por la divulgación progresiva: veinticinco campos en una
 * sola pantalla se abandonan a la mitad. Nada se guarda hasta el último, así
 * que retroceder no cuesta nada y por eso se puede.
 *
 * Dos cosas que este componente **no** hace:
 *
 * - **No digita el estado.** No hay campo de estado en ninguno de los pasos: al
 *   guardar queda activo solo (regla 5.3). En el resumen se dice, para que quien
 *   registra no lo busque.
 * - **No valida en lugar del servidor.** Lo de aquí solo impide avanzar con un
 *   campo obligatorio vacío, que es más amable que descubrirlo al final. Las
 *   reglas de verdad son las de `RegistrarEmpleado`, y cuando alguna falla el
 *   asistente vuelve al paso donde está el error.
 *
 * El cuarto paso es el resumen. El perfil sociodemográfico —que el documento 3
 * numera como paso 4— se intercala antes de él en F1-06, y solo cuando la
 * empresa lo tenga habilitado.
 */
const props = defineProps({
    opciones: { type: Object, required: true },
    ubicaciones: { type: Object, required: true },
});

const colombia = props.ubicaciones.codigoDeColombia;

const form = useForm({
    tipo_documento: "",
    numero_documento: "",
    genero: "",
    primer_nombre: "",
    segundo_nombre: "",
    primer_apellido: "",
    segundo_apellido: "",
    fecha_nacimiento: "",
    nacimiento: { pais: colombia, departamento: "", ciudad: "" },
    fecha_expedicion_documento: "",
    expedicion: { pais: colombia, departamento: "", ciudad: "" },

    direccion: "",
    residencia: { pais: colombia, departamento: "", ciudad: "" },
    eps_id: "",
    fondo_pension_id: "",
    arl_id: "",
    tipo_riesgo: "",
    caja_compensacion_id: "",

    cargo: "",
    tipo_contrato: "",
    fecha_ingreso: "",
    fecha_fin: "",
    salario: "",
    bonificacion: "0",
    banco_id: "",
});

// Solo el término fijo lleva fecha de finalización (documento 1, 5.4). El campo
// no se deshabilita: desaparece, para que no quede un valor escrito antes de
// cambiar de modalidad.
const llevaFechaFin = computed(
    () => form.tipo_contrato === props.opciones.contratoConFechaFin,
);

/*
 * Qué campo obligatorio vive en qué paso. Sirve para las dos direcciones: no
 * dejar avanzar con uno vacío, y saber a qué paso volver cuando el servidor
 * devuelve un error.
 */
const pasos = computed(() => [
    {
        titulo: "Identificación",
        campos: [
            "tipo_documento",
            "numero_documento",
            "genero",
            "primer_nombre",
            "primer_apellido",
            "fecha_nacimiento",
            "nacimiento.pais",
            "nacimiento.departamento",
            "nacimiento.ciudad",
            "fecha_expedicion_documento",
            "expedicion.pais",
            "expedicion.departamento",
            "expedicion.ciudad",
        ],
    },
    {
        titulo: "Residencia y seguridad social",
        campos: [
            "direccion",
            "residencia.departamento",
            "residencia.ciudad",
            "eps_id",
            "fondo_pension_id",
            "arl_id",
            "tipo_riesgo",
            "caja_compensacion_id",
        ],
    },
    {
        titulo: "Información contractual",
        campos: [
            "cargo",
            "tipo_contrato",
            "fecha_ingreso",
            ...(llevaFechaFin.value ? ["fecha_fin"] : []),
            "salario",
            "bonificacion",
            "banco_id",
        ],
    },
    { titulo: "Resumen", campos: [] },
]);

const paso = ref(0);

const titulos = computed(() => pasos.value.map((uno) => uno.titulo));

const esElResumen = computed(() => paso.value === pasos.value.length - 1);

function valorDe(campo) {
    return campo
        .split(".")
        .reduce((dato, parte) => (dato ?? {})[parte], form);
}

function textoDe(opciones, valor) {
    const opcion = opciones.find(
        (una) => String(una.valor) === String(valor),
    );

    return opcion ? opcion.texto : "";
}

/*
 * Avanza si el paso está completo. Lo que falta se marca campo por campo y no
 * con un mensaje general arriba: «faltan datos» obliga a buscarlos.
 */
function continuar() {
    const faltantes = pasos.value[paso.value].campos.filter(
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

function volver(indice) {
    if (indice <= paso.value) {
        paso.value = indice;
    }
}

function guardar() {
    form.post("/empleados", {
        onError: (errores) => {
            const primero = pasos.value.findIndex((uno) =>
                uno.campos.some((campo) => campo in errores),
            );

            paso.value = primero === -1 ? paso.value : primero;
        },
    });
}

const nombreCompleto = computed(() =>
    [
        form.primer_nombre,
        form.segundo_nombre,
        form.primer_apellido,
        form.segundo_apellido,
    ]
        .map((parte) => parte.trim())
        .filter((parte) => parte !== "")
        .join(" "),
);

// Lo que se enseña antes de guardar. Es un repaso, no un formulario: si algo
// está mal se vuelve a su paso, que sigue a un clic de distancia.
const resumen = computed(() => [
    { etiqueta: "Nombre completo", valor: nombreCompleto.value },
    {
        etiqueta: "Documento",
        valor: `${textoDe(props.opciones.documentos, form.tipo_documento)} ${form.numero_documento}`,
    },
    { etiqueta: "Dirección", valor: form.direccion },
    {
        etiqueta: "EPS",
        valor: textoDe(props.opciones.eps, form.eps_id),
    },
    {
        etiqueta: "Fondo de pensión",
        valor: textoDe(props.opciones.fondosPension, form.fondo_pension_id),
    },
    {
        etiqueta: "ARL",
        valor: `${textoDe(props.opciones.arls, form.arl_id)} · ${textoDe(props.opciones.riesgos, form.tipo_riesgo)}`,
    },
    {
        etiqueta: "Caja de compensación",
        valor: textoDe(
            props.opciones.cajasCompensacion,
            form.caja_compensacion_id,
        ),
    },
    { etiqueta: "Cargo", valor: form.cargo },
    {
        etiqueta: "Tipo de contrato",
        valor: textoDe(props.opciones.contratos, form.tipo_contrato),
    },
    { etiqueta: "Fecha de ingreso", valor: form.fecha_ingreso },
    ...(llevaFechaFin.value
        ? [{ etiqueta: "Fecha de finalización", valor: form.fecha_fin }]
        : []),
    { etiqueta: "Salario mensual", valor: form.salario },
    { etiqueta: "Bonificación", valor: form.bonificacion },
    {
        etiqueta: "Entidad bancaria",
        valor: textoDe(props.opciones.bancos, form.banco_id),
    },
]);
</script>

<template>
    <Head title="Nuevo empleado" />

    <Armazon>
        <Link href="/empleados" class="text-apoyo text-accion">
            ← Cancelar y volver
        </Link>

        <h1 class="mt-2 text-titulo font-semibold text-texto">
            Nuevo empleado
        </h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            Paso {{ paso + 1 }} de {{ pasos.length }} · Los datos se guardan al
            terminar
        </p>

        <PasosDelAsistente :titulos="titulos" :actual="paso" @ir="volver" />

        <form class="mt-5" @submit.prevent="esElResumen ? guardar() : continuar()">
            <!-- Paso 1 · Identificación -->
            <div
                v-show="paso === 0"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Identificación
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <Selector
                        v-model="form.tipo_documento"
                        etiqueta="Tipo de documento"
                        :opciones="opciones.documentos"
                        :error="form.errors.tipo_documento ?? ''"
                    />

                    <CampoTexto
                        v-model="form.numero_documento"
                        etiqueta="Número de documento"
                        ayuda="Es la llave con la que se vincula toda la información de la persona."
                        :error="form.errors.numero_documento ?? ''"
                    />

                    <Selector
                        v-model="form.genero"
                        etiqueta="Género"
                        :opciones="opciones.generos"
                        :error="form.errors.genero ?? ''"
                    />
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <CampoTexto
                        v-model="form.primer_nombre"
                        etiqueta="Primer nombre"
                        :error="form.errors.primer_nombre ?? ''"
                    />

                    <CampoTexto
                        v-model="form.segundo_nombre"
                        etiqueta="Segundo nombre"
                        opcional
                        :error="form.errors.segundo_nombre ?? ''"
                    />

                    <CampoTexto
                        v-model="form.primer_apellido"
                        etiqueta="Primer apellido"
                        :error="form.errors.primer_apellido ?? ''"
                    />

                    <CampoTexto
                        v-model="form.segundo_apellido"
                        etiqueta="Segundo apellido"
                        opcional
                        :error="form.errors.segundo_apellido ?? ''"
                    />
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <CampoFecha
                        v-model="form.fecha_nacimiento"
                        etiqueta="Fecha de nacimiento"
                        :error="form.errors.fecha_nacimiento ?? ''"
                    />
                </div>

                <div class="mt-4">
                    <SelectorUbicacion
                        v-model="form.nacimiento"
                        sufijo="de nacimiento"
                        :paises="ubicaciones.paises"
                        :departamentos="ubicaciones.departamentos"
                        :codigo-de-colombia="ubicaciones.codigoDeColombia"
                        :errores="{
                            pais: form.errors['nacimiento.pais'] ?? '',
                            departamento:
                                form.errors['nacimiento.departamento'] ?? '',
                            ciudad: form.errors['nacimiento.ciudad'] ?? '',
                        }"
                    />
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <CampoFecha
                        v-model="form.fecha_expedicion_documento"
                        etiqueta="Fecha de expedición del documento"
                        :error="form.errors.fecha_expedicion_documento ?? ''"
                    />
                </div>

                <div class="mt-4">
                    <SelectorUbicacion
                        v-model="form.expedicion"
                        sufijo="de expedición del documento"
                        :paises="ubicaciones.paises"
                        :departamentos="ubicaciones.departamentos"
                        :codigo-de-colombia="ubicaciones.codigoDeColombia"
                        :errores="{
                            pais: form.errors['expedicion.pais'] ?? '',
                            departamento:
                                form.errors['expedicion.departamento'] ?? '',
                            ciudad: form.errors['expedicion.ciudad'] ?? '',
                        }"
                    />
                </div>
            </div>

            <!-- Paso 2 · Residencia y seguridad social -->
            <div
                v-show="paso === 1"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">Residencia</h2>

                <Aviso class="mt-3">
                    La residencia es siempre en Colombia: el sistema solo
                    gestiona contratos laborales colombianos.
                </Aviso>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <CampoTexto
                        v-model="form.direccion"
                        etiqueta="Dirección"
                        :error="form.errors.direccion ?? ''"
                    />
                </div>

                <div class="mt-4">
                    <SelectorUbicacion
                        v-model="form.residencia"
                        sufijo="de residencia"
                        solo-colombia
                        :paises="ubicaciones.paises"
                        :departamentos="ubicaciones.departamentos"
                        :codigo-de-colombia="ubicaciones.codigoDeColombia"
                        :errores="{
                            departamento:
                                form.errors['residencia.departamento'] ?? '',
                            ciudad: form.errors['residencia.ciudad'] ?? '',
                        }"
                    />
                </div>

                <h2 class="mt-6 text-seccion font-semibold text-texto">
                    Seguridad social
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <Selector
                        v-model="form.eps_id"
                        etiqueta="EPS"
                        :opciones="opciones.eps"
                        :error="form.errors.eps_id ?? ''"
                    />

                    <Selector
                        v-model="form.fondo_pension_id"
                        etiqueta="Fondo de pensión"
                        :opciones="opciones.fondosPension"
                        :error="form.errors.fondo_pension_id ?? ''"
                    />

                    <Selector
                        v-model="form.arl_id"
                        etiqueta="ARL"
                        :opciones="opciones.arls"
                        :error="form.errors.arl_id ?? ''"
                    />

                    <Selector
                        v-model="form.tipo_riesgo"
                        etiqueta="Tipo de riesgo"
                        :opciones="opciones.riesgos"
                        :error="form.errors.tipo_riesgo ?? ''"
                    />

                    <Selector
                        v-model="form.caja_compensacion_id"
                        etiqueta="Caja de compensación"
                        :opciones="opciones.cajasCompensacion"
                        :error="form.errors.caja_compensacion_id ?? ''"
                    />
                </div>
            </div>

            <!-- Paso 3 · Información contractual -->
            <div
                v-show="paso === 2"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Información laboral y contractual
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <CampoTexto
                        v-model="form.cargo"
                        etiqueta="Cargo"
                        :error="form.errors.cargo ?? ''"
                    />

                    <Selector
                        v-model="form.tipo_contrato"
                        etiqueta="Tipo de contrato"
                        :opciones="opciones.contratos"
                        :error="form.errors.tipo_contrato ?? ''"
                    />

                    <CampoFecha
                        v-model="form.fecha_ingreso"
                        etiqueta="Fecha de ingreso"
                        ayuda="Sobre ella se calculan las vacaciones y el periodo de prueba."
                        :error="form.errors.fecha_ingreso ?? ''"
                    />

                    <CampoFecha
                        v-if="llevaFechaFin"
                        v-model="form.fecha_fin"
                        etiqueta="Fecha de finalización"
                        :error="form.errors.fecha_fin ?? ''"
                    />

                    <CampoTexto
                        v-model="form.salario"
                        etiqueta="Salario mensual"
                        tipo="number"
                        :error="form.errors.salario ?? ''"
                    />

                    <CampoTexto
                        v-model="form.bonificacion"
                        etiqueta="Bonificación"
                        tipo="number"
                        ayuda="Puede quedar en cero."
                        :error="form.errors.bonificacion ?? ''"
                    />

                    <Selector
                        v-model="form.banco_id"
                        etiqueta="Entidad bancaria"
                        :opciones="opciones.bancos"
                        ayuda="Solo la entidad, no el número de cuenta."
                        :error="form.errors.banco_id ?? ''"
                    />
                </div>
            </div>

            <!-- Paso 4 · Resumen -->
            <div
                v-show="esElResumen"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Revisa antes de guardar
                </h2>

                <dl class="mt-4 divide-y divide-borde">
                    <div
                        v-for="linea in resumen"
                        :key="linea.etiqueta"
                        class="flex justify-between gap-4 py-2 text-tabla"
                    >
                        <dt class="text-texto-secundario">
                            {{ linea.etiqueta }}
                        </dt>
                        <dd class="text-right font-medium text-texto">
                            {{ linea.valor }}
                        </dd>
                    </div>
                </dl>

                <Aviso class="mt-4">
                    El empleado quedará <strong>activo</strong> en cuanto se
                    guarde. El estado no se digita: cambia a inactivo solo cuando
                    se registre su salida.
                </Aviso>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <Link v-if="paso === 0" href="/empleados">
                    <Boton tipo="button" variante="texto">Cancelar</Boton>
                </Link>

                <Boton
                    v-else
                    tipo="button"
                    variante="secundario"
                    @click="volver(paso - 1)"
                >
                    ← Volver
                </Boton>

                <Boton v-if="!esElResumen" tipo="submit">Continuar →</Boton>

                <Boton v-else tipo="submit" :enviando="form.processing">
                    Guardar empleado
                </Boton>
            </div>
        </form>
    </Armazon>
</template>
