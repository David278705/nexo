<script setup>
import { useForm } from "@inertiajs/vue3";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import Modal from "@/Components/Modal.vue";

/*
 * El modal de «Cambiar a término indefinido» (documento 3, sección 5.4).
 *
 * No pide ni un dato: es una confirmación. Y como es irreversible, la
 * confirmación explica **la consecuencia calculada** y no pregunta «¿está
 * seguro?», que es la regla del documento 3, sección 6.2, para las acciones que
 * no se pueden deshacer.
 *
 * **Ninguna de las frases se escribe aquí.** Las cinco implicaciones, la entrada
 * y la advertencia llegan desde `CambioAIndefinido` en el servidor, que es quien
 * conoce la fecha de ingreso y la fecha del cambio. Escritas en el navegador, la
 * pantalla podría prometer una cosa y el servidor hacer otra.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    // La dirección del detalle. La acción cuelga de ella.
    url: { type: String, required: true },
    cambio: { type: Object, required: true },
});

const emit = defineEmits(["cerrar"]);

// Un formulario sin campos: lo único que viaja es la intención. `useForm` se usa
// igual porque de él vienen `processing` —que deshabilita el botón mientras
// envía— y la visita de Inertia.
const form = useForm({});

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
