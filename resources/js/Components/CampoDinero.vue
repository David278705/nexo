<script setup>
import { ref, watch } from "vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import { formatearPesos, leerPesos } from "@/Utilidades/pesos.js";

/*
 * Un campo de dinero escrito como se escribe en Colombia: punto para los miles
 * y coma para los centavos (`PROD-11`).
 *
 * Existe por un error concreto. Con un campo `number` del navegador, quien
 * escribía «380.000» —trescientos ochenta mil pesos, a la colombiana— mandaba
 * 380, y el servidor no podía distinguirlo de trescientos ochenta pesos.
 *
 * Lo que se ve y lo que se envía son dos cosas distintas, a propósito:
 *
 * - **Lo que se ve** es lo que la persona escribe mientras escribe, sin tocarle
 *   el cursor, y se pone en limpio —«$ 380.000»— al salir del campo, para que
 *   lo que quedó escrito sea inequívoco antes de guardar.
 * - **Lo que se envía** (`modelValue`) es el número limpio con punto decimal
 *   —«380000», «380000.50»—, que es lo que valida `decimal:0,2` en el servidor.
 *
 * El punto se lee siempre como separador de miles. Es la convención del país,
 * y por eso al salir el campo enseña el valor formateado: si alguien escribió
 * «3.5» pensando en tres y medio, ve «$ 35» antes de guardar.
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    etiqueta: { type: String, required: true },
    error: { type: String, default: "" },
    ayuda: { type: String, default: "" },
    opcional: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "salir"]);

const visible = ref(formatearPesos(props.modelValue));

// Un cambio que viene de fuera —el formulario que se reinicia tras guardar—
// se refleja; el eco de lo que esta misma caja acaba de enviar, no, para no
// reformatear bajo el cursor de quien escribe.
watch(
    () => props.modelValue,
    (valor) => {
        if (String(valor ?? "") !== leerPesos(visible.value)) {
            visible.value = formatearPesos(valor);
        }
    },
);

function alEscribir(texto) {
    visible.value = texto;
    emit("update:modelValue", leerPesos(texto));
}

function alSalir() {
    const limpio = leerPesos(visible.value);
    visible.value = formatearPesos(limpio);
    emit("salir", limpio);
}
</script>

<template>
    <CampoTexto
        :model-value="visible"
        :etiqueta="etiqueta"
        :error="error"
        :ayuda="ayuda"
        :opcional="opcional"
        modo-teclado="decimal"
        @update:model-value="alEscribir"
        @salir="alSalir"
    />
</template>
