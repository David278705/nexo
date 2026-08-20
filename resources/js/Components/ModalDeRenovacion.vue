<script setup>
import { router, useForm } from "@inertiajs/vue3";
import { watch } from "vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Modal from "@/Components/Modal.vue";
import Selector from "@/Components/Selector.vue";

/*
 * El modal de «Renovar contrato» (documento 3, sección 5.4).
 *
 * Enseña el periodo vigente, pide el tipo de prórroga y la nueva fecha de
 * finalización, y debajo muestra la duración de la prórroga, el total acumulado
 * del vínculo y las prórrogas que quedarán.
 *
 * **Aquí no se cuenta ni un día.** Las tres cuentas las hace
 * `RenovacionDeContrato` en el servidor y llegan en `renovacion.proyeccion` ya
 * escritas: al cambiar la fecha o el tipo se vuelve a pedir esa sola prop con
 * una recarga parcial. Un cálculo repetido en el navegador se separaría del del
 * servidor a la primera corrección, y el número que el usuario lee tiene que ser
 * el mismo que se va a guardar.
 *
 * El aviso de arriba es el del prototipo y explica la diferencia que la sección
 * 5.5 exige que se vea: renovar no es corregir la fecha.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    // La dirección del detalle, que es donde se recalcula la proyección.
    url: { type: String, required: true },
    renovacion: { type: Object, required: true },
});

const emit = defineEmits(["cerrar"]);

const form = useForm({
    tipo: props.renovacion.propuesta?.tipo ?? "",
    fecha_fin: props.renovacion.propuesta?.fecha_fin ?? "",
});

/*
 * Cada cambio de la fecha o del tipo pide al servidor la proyección de nuevo.
 * `only` trae únicamente la prop de la renovación, y `preserveState` deja el
 * modal abierto con lo digitado dentro.
 *
 * Se observan los dos campos en vez de escuchar el evento de cada control: así
 * la petición sale siempre con el valor que el formulario ya tiene, sin depender
 * de en qué orden se ejecuten los oyentes de `v-model`.
 */
watch(
    () => [form.tipo, form.fecha_fin],
    ([tipo, fecha_fin]) => {
        router.get(
            props.url,
            { tipo, fecha_fin },
            {
                only: ["renovacion"],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    },
);

// Al cerrar el modal se limpian los errores del intento anterior: volver a
// abrirlo tiene que sentirse como empezar, no como retomar un error.
watch(
    () => props.abierto,
    (abierto) => {
        if (!abierto) {
            form.clearErrors();
        }
    },
);

function registrar() {
    form.post(`${props.url}/renovar`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit("cerrar");
        },
    });
}
</script>

<template>
    <Modal
        :abierto="abierto"
        titulo="Renovar contrato"
        @cerrar="emit('cerrar')"
    >
        <Aviso>
            Renovar es distinto de corregir la fecha. Si la fecha de finalización
            está mal digitada, edita el contrato. Renovar registra una prórroga
            nueva, que es un hecho jurídico y agrega una fila al historial.
        </Aviso>

        <div class="mt-4">
            <ListaDeDatos :campos="renovacion.resumen" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <Selector
                v-model="form.tipo"
                etiqueta="Tipo de prórroga"
                :opciones="renovacion.tipos"
                :error="form.errors.tipo"
                ayuda="El límite de prórrogas cortas es distinto en cada caso."
            />

            <CampoFecha
                v-model="form.fecha_fin"
                etiqueta="Nueva fecha de finalización"
                :error="form.errors.fecha_fin"
                :ayuda="`El nuevo periodo empieza el ${renovacion.inicio}.`"
            />
        </div>

        <div v-if="renovacion.proyeccion" class="mt-4">
            <Aviso :tono="renovacion.proyeccion.tono">
                <p
                    v-for="linea in renovacion.proyeccion.lineas"
                    :key="linea"
                    class="mt-1 first:mt-0"
                >
                    {{ linea }}
                </p>
            </Aviso>
        </div>

        <template #pie>
            <Boton variante="texto" tipo="button" @click="emit('cerrar')">
                Cancelar
            </Boton>
            <Boton tipo="button" :enviando="form.processing" @click="registrar">
                Registrar prórroga
            </Boton>
        </template>
    </Modal>
</template>
