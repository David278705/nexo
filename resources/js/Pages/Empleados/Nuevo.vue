<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import AvisoDeReingreso from "@/Components/AvisoDeReingreso.vue";
import Boton from "@/Components/Boton.vue";
import CampoDinero from "@/Components/CampoDinero.vue";
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
 * El último paso es siempre el resumen. Antes de él se intercala el perfil
 * sociodemográfico (documento 1, 5.2) cuando la empresa lo tiene habilitado:
 * entonces el asistente tiene cinco pasos y no cuatro. Lo decide el servidor,
 * no esta pantalla.
 */
const props = defineProps({
    opciones: { type: Object, required: true },
    ubicaciones: { type: Object, required: true },
    // La advertencia de reingreso (documento 1, 5.6). Llega vacía con la
    // pantalla y solo se llena cuando el servidor la calcula, en `consultar()`.
    reingreso: { type: Object, default: null },
    // El parámetro de la empresa (documento 1, capítulo 4). Se cambia en la
    // pantalla de parámetros, no aquí.
    perfilSociodemografico: { type: Boolean, default: false },
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
    celular: "",
    correo: "",
    eps_id: "",
    fondo_pension_id: "",
    arl_id: "",
    tipo_riesgo: "",
    caja_compensacion_id: "",

    cargo: "",
    tipo_contrato: "",
    fecha_ingreso: "",
    fecha_fin: "",
    fecha_fin_estimada: "",
    salario: "",
    bonificacion: "0",
    banco_id: "",

    // El bloque de 5.2. Viaja siempre, habilitado o no: si la empresa lo tiene
    // apagado va vacío y el servidor no escribe nada con él.
    perfil: {
        estado_civil: "",
        nivel_educativo: "",
        numero_hijos: "",
        personas_a_cargo: "",
        tipo_vivienda: "",
        estrato: "",
        medio_transporte: "",
        tiempo_desplazamiento: "",
    },
});

// Solo el término fijo lleva fecha de finalización (documento 1, 5.4). El campo
// no se deshabilita: desaparece, para que no quede un valor escrito antes de
// cambiar de modalidad.
const llevaFechaFin = computed(
    () => form.tipo_contrato === props.opciones.contratoConFechaFin,
);

// La obra o labor admite una fecha de finalización estimada, siempre opcional
// (`OL-01`). Igual que la del fijo, el campo desaparece al cambiar de modalidad,
// y el servidor descarta lo que llegue en otra.
const llevaFechaEstimada = computed(
    () => form.tipo_contrato === props.opciones.contratoConFechaEstimada,
);

/*
 * La comprobación de reingreso (documento 1, 5.6).
 *
 * Se pregunta al servidor al salir del campo del documento, y otra vez al salir
 * del de la fecha de ingreso, porque el énfasis de los 30 días depende de las
 * dos cosas y la fecha se digita dos pasos después.
 *
 * Sin `axios` ni `fetch`: `router.reload` con `only` trae únicamente la prop
 * `reingreso` y deja intacto lo que ya está escrito en el formulario.
 */
const ultimaConsulta = ref("");

function consultar() {
    const documento = form.numero_documento.trim();
    const consulta = `${documento}|${form.fecha_ingreso}`;

    if (documento === "" || consulta === ultimaConsulta.value) {
        return;
    }

    ultimaConsulta.value = consulta;

    router.reload({
        only: ["reingreso"],
        data: {
            numero_documento: documento,
            fecha_ingreso: form.fecha_ingreso,
        },
    });
}

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
            "celular",
            "correo",
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
    /*
     * El perfil sociodemográfico va antes del resumen y solo si la empresa lo
     * habilitó. Su lista de campos está vacía a propósito: **todos sus campos
     * son opcionales aunque el bloque esté activo** (documento 1, 5.2), así que
     * no hay nada que impida continuar ni nada que marcar como faltante.
     */
    ...(props.perfilSociodemografico
        ? [{ titulo: "Perfil sociodemográfico", campos: [] }]
        : []),
    { titulo: "Resumen", campos: [] },
]);

const paso = ref(0);

// El paso del perfil, cuando existe. Es el penúltimo: el resumen siempre cierra.
const indiceDelPerfil = computed(() =>
    props.perfilSociodemografico ? pasos.value.length - 2 : -1,
);

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
    { etiqueta: "Celular", valor: form.celular },
    { etiqueta: "Correo electrónico", valor: form.correo },
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
    ...(llevaFechaEstimada.value
        ? [
              {
                  etiqueta: "Fecha de finalización estimada",
                  valor:
                      form.fecha_fin_estimada ||
                      "Sin registrar: no se calcula periodo de prueba",
              },
          ]
        : []),
    { etiqueta: "Salario mensual", valor: form.salario },
    { etiqueta: "Bonificación", valor: form.bonificacion },
    {
        etiqueta: "Entidad bancaria",
        valor: textoDe(props.opciones.bancos, form.banco_id),
    },
]);

/*
 * El repaso del bloque opcional. Solo se enseña lo que se llenó: una lista de
 * ocho renglones vacíos parecería un formulario a medio hacer, y aquí quedar en
 * blanco es una respuesta válida.
 */
const resumenDelPerfil = computed(() =>
    [
        {
            etiqueta: "Estado civil",
            valor: textoDe(props.opciones.estadosCiviles, form.perfil.estado_civil),
        },
        {
            etiqueta: "Nivel educativo",
            valor: textoDe(
                props.opciones.nivelesEducativos,
                form.perfil.nivel_educativo,
            ),
        },
        { etiqueta: "Número de hijos", valor: form.perfil.numero_hijos },
        {
            etiqueta: "Personas a cargo",
            valor: form.perfil.personas_a_cargo,
        },
        {
            etiqueta: "Tipo de vivienda",
            valor: textoDe(props.opciones.tiposVivienda, form.perfil.tipo_vivienda),
        },
        {
            etiqueta: "Estrato socioeconómico",
            valor: textoDe(props.opciones.estratos, form.perfil.estrato),
        },
        {
            etiqueta: "Medio de transporte",
            valor: textoDe(
                props.opciones.mediosTransporte,
                form.perfil.medio_transporte,
            ),
        },
        {
            etiqueta: "Tiempo de desplazamiento",
            valor: textoDe(
                props.opciones.tiemposDesplazamiento,
                form.perfil.tiempo_desplazamiento,
            ),
        },
    ].filter((linea) => String(linea.valor ?? "").trim() !== ""),
);
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
                        @salir="consultar"
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

                <!--
                    El aviso va aquí, pegado al campo que lo dispara: quien
                    digita el documento se entera en el sitio donde lo digitó.
                -->
                <AvisoDeReingreso :reingreso="reingreso" class="mt-4" />

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

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <CampoTexto
                        v-model="form.celular"
                        etiqueta="Celular"
                        ayuda="Diez dígitos, sin espacios ni guiones."
                        :error="form.errors.celular ?? ''"
                    />

                    <CampoTexto
                        v-model="form.correo"
                        etiqueta="Correo electrónico"
                        tipo="email"
                        :error="form.errors.correo ?? ''"
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
                        @salir="consultar"
                    />

                    <CampoFecha
                        v-if="llevaFechaFin"
                        v-model="form.fecha_fin"
                        etiqueta="Fecha de finalización"
                        :error="form.errors.fecha_fin ?? ''"
                    />

                    <CampoFecha
                        v-if="llevaFechaEstimada"
                        v-model="form.fecha_fin_estimada"
                        etiqueta="Fecha de finalización estimada"
                        ayuda="De ella sale el periodo de prueba sugerido."
                        opcional
                        :error="form.errors.fecha_fin_estimada ?? ''"
                    />

                    <CampoDinero
                        v-model="form.salario"
                        etiqueta="Salario mensual"
                        :error="form.errors.salario ?? ''"
                    />

                    <CampoDinero
                        v-model="form.bonificacion"
                        etiqueta="Bonificación"
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

                <!--
                    El aviso de la obra o labor (`OL-01`). Los tres textos llegan
                    del servidor, de `FechaFinEstimada`, donde una prueba comprueba
                    que siguen diciendo lo que dicen: son los que dejan claro que el
                    periodo de prueba es una sugerencia sobre una fecha del cliente.
                -->
                <Aviso
                    v-if="llevaFechaEstimada"
                    tono="advertencia"
                    :titulo="opciones.avisoDeObraOLabor.titulo"
                    class="mt-4"
                >
                    <p>{{ opciones.avisoDeObraOLabor.explicacion }}</p>
                    <p class="mt-2 font-semibold">
                        {{ opciones.avisoDeObraOLabor.riesgo }}
                    </p>
                </Aviso>
            </div>

            <!-- Paso 4 · Perfil sociodemográfico, solo si la empresa lo habilitó -->
            <div
                v-if="perfilSociodemografico"
                v-show="paso === indiceDelPerfil"
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Perfil sociodemográfico
                </h2>

                <Aviso class="mt-3">
                    <strong>Todos estos campos son opcionales.</strong> Se pueden
                    dejar en blanco y completar después. Son el insumo que el
                    Sistema de Gestión de Seguridad y Salud en el Trabajo pide
                    para el diagnóstico de condiciones de salud.
                </Aviso>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <Selector
                        v-model="form.perfil.estado_civil"
                        etiqueta="Estado civil"
                        opcional
                        :opciones="opciones.estadosCiviles"
                        :error="form.errors['perfil.estado_civil'] ?? ''"
                    />

                    <Selector
                        v-model="form.perfil.nivel_educativo"
                        etiqueta="Nivel educativo alcanzado"
                        opcional
                        :opciones="opciones.nivelesEducativos"
                        :error="form.errors['perfil.nivel_educativo'] ?? ''"
                    />

                    <CampoTexto
                        v-model="form.perfil.numero_hijos"
                        etiqueta="Número de hijos"
                        tipo="number"
                        opcional
                        :error="form.errors['perfil.numero_hijos'] ?? ''"
                    />

                    <CampoTexto
                        v-model="form.perfil.personas_a_cargo"
                        etiqueta="Número de personas a cargo"
                        tipo="number"
                        opcional
                        :error="form.errors['perfil.personas_a_cargo'] ?? ''"
                    />

                    <Selector
                        v-model="form.perfil.tipo_vivienda"
                        etiqueta="Tipo de vivienda"
                        opcional
                        :opciones="opciones.tiposVivienda"
                        :error="form.errors['perfil.tipo_vivienda'] ?? ''"
                    />

                    <Selector
                        v-model="form.perfil.estrato"
                        etiqueta="Estrato socioeconómico"
                        opcional
                        :opciones="opciones.estratos"
                        :error="form.errors['perfil.estrato'] ?? ''"
                    />

                    <Selector
                        v-model="form.perfil.medio_transporte"
                        etiqueta="Medio de transporte al trabajo"
                        opcional
                        :opciones="opciones.mediosTransporte"
                        :error="form.errors['perfil.medio_transporte'] ?? ''"
                    />

                    <Selector
                        v-model="form.perfil.tiempo_desplazamiento"
                        etiqueta="Tiempo de desplazamiento al trabajo"
                        opcional
                        :opciones="opciones.tiemposDesplazamiento"
                        :error="form.errors['perfil.tiempo_desplazamiento'] ?? ''"
                    />
                </div>
            </div>

            <!-- Último paso · Resumen -->
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

                <template v-if="perfilSociodemografico">
                    <h3 class="mt-5 text-apoyo font-semibold text-texto">
                        Perfil sociodemográfico
                    </h3>

                    <dl
                        v-if="resumenDelPerfil.length > 0"
                        class="mt-2 divide-y divide-borde"
                    >
                        <div
                            v-for="linea in resumenDelPerfil"
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

                    <p v-else class="mt-2 text-tabla text-texto-secundario">
                        Sin diligenciar. Son campos opcionales: el empleado se
                        guarda igual y se pueden completar después.
                    </p>
                </template>

                <!--
                    Se repite en el resumen a propósito: aquí ya se sabe la
                    fecha de ingreso, así que es donde el énfasis de los 30 días
                    puede aparecer, y es el último momento antes de guardar.
                -->
                <AvisoDeReingreso :reingreso="reingreso" class="mt-4" />

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
