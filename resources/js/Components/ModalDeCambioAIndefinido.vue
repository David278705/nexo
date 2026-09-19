<script setup>
import { router, useForm } from "@inertiajs/vue3";
import { watch } from "vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import Modal from "@/Components/Modal.vue";

/*
 * El modal de «Cambiar a término indefinido» (documento 3, sección 5.4).
 *
 * Pide un solo dato: la fecha del cambio (HD-11), propuesta en hoy y editable
 * hacia atrás hasta el inicio del periodo vigente. Sigue sin preguntar «¿está
 * seguro?»: como es irreversible, la confirmación explica **la consecuencia
 * calculada**, que es la regla del documento 3, sección 6.2.
 *
 * **Ninguna de las frases se escribe aquí.** Las cinco implicaciones, la entrada
 * y la advertencia llegan desde `CambioAIndefinido` en el servidor, que es quien
 * conoce la fecha de ingreso y la fecha del cambio. Cada vez que la fecha
 * digitada cambia, se pide de nuevo esa misma prop con una recarga parcial —
 * igual que hace `ModalDeRenovacion`—, así que la quinta implicación siempre
 * dice la fecha que de verdad se va a guardar, nunca «hoy» a secas.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    // La dirección del detalle. La acción cuelga de ella.
    url: { type: String, required: true },
    cambio: { type: Object, required: true },
});

const emit = defineEmits(["cerrar"]);

const form = useForm({
    fecha: props.cambio.fecha,
});

watch(
    () => props.cambio.fecha,
    (fecha) => {
        form.fecha = fecha;
    },
);

watch(
    () => form.fecha,
    (fecha) => {
        router.get(
            props.url,
            { fecha },
            {
                only: ["cambioAIndefinido"],
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

function confirmar() {
    form.post(`${props.url}/indefinido`, {
        preserveScroll: true,
        onSuccess: () => emit("cerrar"),
    });
}
</script>

<template>
    <Modal
        :abierto="abierto"
        titulo="Cambiar a término indefinido"
        @cerrar="emit('cerrar')"
    >
        <p class="text-texto">{{ cambio.entrada }}</p>

        <div class="mt-4">
            <CampoFecha
                v-model="form.fecha"
                etiqueta="Fecha del cambio"
                :error="form.errors.fecha"
                :minimo="cambio.minimo"
                :maximo="cambio.maximo"
                ayuda="No puede ser anterior al inicio del periodo vigente ni posterior a hoy."
            />
        </div>

        <div class="mt-4">
            <Aviso :titulo="cambio.titulo">
                <ul class="list-disc space-y-1 pl-5">
                    <li
                        v-for="implicacion in cambio.implicaciones"
                        :key="implicacion"
                    >
                        {{ implicacion }}
                    </li>
                </ul>
            </Aviso>
        </div>

        <div class="mt-4">
            <Aviso tono="advertencia">{{ cambio.advertencia }}</Aviso>
        </div>

        <template #pie>
            <Boton variante="texto" tipo="button" @click="emit('cerrar')">
                Cancelar
            </Boton>
            <Boton tipo="button" :enviando="form.processing" @click="confirmar">
                Confirmar cambio
            </Boton>
        </template>
    </Modal>
</template>
