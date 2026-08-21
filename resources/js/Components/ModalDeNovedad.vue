<script setup>
import { router } from "@inertiajs/vue3";
import { computed, nextTick, ref, watch } from "vue";
import Boton from "@/Components/Boton.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Modal from "@/Components/Modal.vue";
import ResumenDeEmpleado from "@/Components/ResumenDeEmpleado.vue";

/*
 * El flujo compartido de registro de novedades (documento 3, sección 5.5).
 *
 * Es **un solo componente para los cinco módulos** —incapacidades, vacaciones,
 * licencias, movimientos y terminaciones—, porque el documento dice que las
 * subsecciones «comparten exactamente la misma estructura: aprender una es
 * aprender las tres». Si cada módulo escribiera su propio modal, dejarían de
 * compartirla a la tercera corrección.
 *
 * Los cinco pasos del documento, en orden:
 *
 * 1. El listado pulsa Nuevo y abre este componente con `abierto`.
 * 2. Modal pequeño que pide **solo** el número de documento.
 * 3. **Al salir del campo** —no al pulsar Continuar— se pregunta al servidor. Si
 *    no existe o no está activo, el mensaje sale debajo del campo y Continuar
 *    queda deshabilitado.
 * 4. Si existe, el modal pequeño se reemplaza por el grande: arriba la ficha del
 *    empleado y debajo el formulario del módulo, que llega por el `slot`.
 * 5. Los días los calcula el servidor y los pinta el módulo en su `slot`. **Aquí
 *    no se cuenta ni un día ni se calcula ningún saldo.**
 *
 * ## De dónde salen los datos
 *
 * De la prop compartida `empleadoDeNovedad`, que se pide con una recarga parcial
 * de la pantalla actual. Sin `axios` ni `fetch`, que es la regla de interfaz. El
 * componente no busca ni valida: pregunta y pinta lo que le contesten.
 *
 * ## Qué pone cada módulo
 *
 * El `slot` por defecto, con sus campos, y el `slot` `pie` si necesita algo más
 * que Cancelar y Guardar. El módulo recibe el empleado encontrado —con el
 * identificador de la vinculación, que es lo que va a guardar— por el `slot` y
 * por el evento `seleccionado`.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    // El título de los cinco lo decide `TipoDeNovedad` en el servidor, así que
    // ninguno puede quedar diciendo algo distinto.
    titulo: { type: String, required: true },
    // La etiqueta y la ayuda del campo del documento: prop `flujoDeNovedad`.
    flujo: { type: Object, required: true },
    // La respuesta del servidor al documento digitado: prop `empleadoDeNovedad`.
    // Nula mientras no se haya preguntado nada.
    empleado: { type: Object, default: null },
    // Mientras el módulo envía la novedad. El botón se deshabilita para evitar
    // el doble registro (documento 3, 6.2).
    enviando: { type: Boolean, default: false },
    // El módulo puede impedir el guardado, como hace la renovación cuando la
    // proyección está bloqueada. Deshabilitar es cortesía: la validación está
    // siempre en el servidor.
    deshabilitado: { type: Boolean, default: false },
});

const emit = defineEmits(["cerrar", "guardar", "seleccionado"]);

const documento = ref("");
const campoDelDocumento = ref(null);
// Mientras no se pulsa Continuar, el flujo sigue en el paso del documento
// aunque el empleado ya se haya encontrado: es lo que hace el prototipo.
const enElFormulario = ref(false);

/*
 * La respuesta del servidor solo se tiene en cuenta si es la del documento que
 * está en el campo ahora. Sin esa comprobación, borrar dos cifras del número
 * dejaría en pantalla la ficha del empleado anterior.
 */
const respuesta = computed(() =>
    props.empleado && props.empleado.documento === documento.value.trim()
        ? props.empleado
        : null,
);

const encontrado = computed(() => respuesta.value?.encontrado === true);
const error = computed(() => respuesta.value?.error ?? "");

/*
 * La consulta al salir del campo (paso 3). `only` trae únicamente la prop del
 * empleado y `preserveState` deja el modal abierto con lo digitado dentro.
 *
 * No se vuelve a preguntar por un documento que ya se preguntó: salir del campo
 * sin haberlo tocado no es un hecho nuevo.
 */
const ultimaConsulta = ref("");

function consultar() {
    const numero = documento.value.trim();

    if (numero === "" || numero === ultimaConsulta.value) {
        return;
    }

    ultimaConsulta.value = numero;

    router.reload({
        only: ["empleadoDeNovedad"],
        data: { documento: numero },
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function continuar() {
    if (!encontrado.value) {
        return;
    }

    enElFormulario.value = true;
    emit("seleccionado", respuesta.value);
}

function guardar() {
    if (props.deshabilitado) {
        return;
    }

    emit("guardar", respuesta.value);
}

/*
 * Volver a abrir el modal tiene que sentirse como empezar, no como retomar: se
 * vuelve al paso del documento y se limpia lo digitado. Al abrirlo, el foco va
 * al campo, que es lo único que hay que hacer ahí.
 */
watch(
    () => props.abierto,
    async (abierto) => {
        documento.value = "";
        ultimaConsulta.value = "";
        enElFormulario.value = false;

        if (abierto) {
            await nextTick();
            campoDelDocumento.value?.$el?.querySelector("input")?.focus();
        }
    },
);
</script>

<template>
    <!-- Paso 2 y 3: el modal pequeño que pide solo el documento. -->
    <Modal
        :abierto="abierto && !enElFormulario"
        :titulo="titulo"
        @cerrar="emit('cerrar')"
    >
        <CampoTexto
            ref="campoDelDocumento"
            v-model="documento"
            :etiqueta="flujo.etiqueta"
            :ayuda="flujo.ayuda"
            :error="error"
            @salir="consultar"
        />

        <template #pie>
            <Boton variante="texto" tipo="button" @click="emit('cerrar')">
                Cancelar
            </Boton>
            <Boton tipo="button" :deshabilitado="!encontrado" @click="continuar">
                Continuar
            </Boton>
        </template>
    </Modal>

    <!-- Paso 4 y 5: la ficha del empleado y el formulario del módulo. -->
    <Modal
        :abierto="abierto && enElFormulario"
        :titulo="titulo"
        ancho="ancho"
        @cerrar="emit('cerrar')"
    >
        <ResumenDeEmpleado v-if="respuesta" :datos="respuesta.datos" />

        <div class="mt-4">
            <slot :empleado="respuesta" />
        </div>

        <template #pie>
            <slot name="pie" :empleado="respuesta">
                <Boton variante="texto" tipo="button" @click="emit('cerrar')">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="enviando"
                    :deshabilitado="deshabilitado"
                    @click="guardar"
                >
                    Guardar
                </Boton>
            </slot>
        </template>
    </Modal>
</template>
