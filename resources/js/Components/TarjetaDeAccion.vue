<script setup>
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

/*
 * Una tarjeta del bloque de acción del panel de inicio (documento 3, 5.2).
 *
 * Lo que la separa de `TarjetaIndicador` no es el aspecto: es que **lleva a un
 * sitio donde se puede actuar**. Un indicador informa y no va a ninguna parte;
 * mezclarlos convierte el panel en un tablero bonito que nadie usa para decidir.
 *
 * Aquí no se cuenta ni se decide nada. La cifra, el texto, el tono y el destino
 * llegan escritos del servidor (`App\Inicio\BloqueDeAccion`), que es el único
 * que puede mirar los datos de la empresa.
 */
const props = defineProps({
    /*
     * Casi siempre un conteo, pero no siempre: el bloque de atención del
     * panel del proveedor (documento 4, 3.1) tiene dos tarjetas de estado
     * —«OK», «2027», «Al día»— que no son un número que se pueda sumar.
     */
    valor: { type: [String, Number], required: true },
    etiqueta: { type: String, required: true },
    enlace: { type: String, required: true },
    accion: { type: String, required: true },
    /*
     * El rojo solo cuando algo está mal de verdad —en el bloque del cliente,
     * el contrato que venció sin que nadie lo gestionara— y el verde solo
     * cuando una tarjeta informa que algo que se vigila está bien, que es lo
     * que necesita el bloque de atención del panel del proveedor (F5-06,
     * documento 4, 3.1): el proceso diario que sí corrió, el calendario de
     * festivos que sí está completo.
     */
    tono: {
        type: String,
        default: "",
        validator: (valor) => ["", "error", "exito"].includes(valor),
    },
    // Las cifras acumuladas que acompañan a la principal, cada una con su
    // propio destino: «7 en 60 días · 9 en 90 días».
    extras: { type: Array, default: () => [] },
});

const estiloDelBorde = computed(
    () =>
        ({
            error: "border-error-borde bg-error-fondo",
            exito: "border-exito-borde bg-exito-fondo",
        })[props.tono] ?? "border-borde bg-superficie",
);

const estiloDeLaCifra = computed(
    () =>
        ({
            error: "text-error-texto",
            exito: "text-exito-texto",
        })[props.tono] ?? "text-texto",
);
</script>

<template>
    <div
        class="rounded-[var(--radius-modal)] border"
        :class="estiloDelBorde"
    >
        <Link
            :href="enlace"
            class="block rounded-[var(--radius-modal)] p-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accion"
        >
            <p
                class="text-cifra font-semibold tabular-nums"
                :class="estiloDeLaCifra"
            >
                {{ valor }}
            </p>
            <p class="mt-1 text-apoyo text-texto-secundario">{{ etiqueta }}</p>

            <p v-if="extras.length === 0" class="mt-2 text-apoyo text-accion">
                {{ accion }} →
            </p>
        </Link>

        <p
            v-if="extras.length > 0"
            class="flex flex-wrap items-center gap-x-2 gap-y-1 px-4 pb-4 text-apoyo"
        >
            <template v-for="(extra, indice) in extras" :key="extra.enlace">
                <span v-if="indice > 0" aria-hidden="true" class="text-texto-terciario">·</span>
                <Link :href="extra.enlace" class="text-accion underline">
                    {{ extra.texto }}
                </Link>
            </template>
        </p>
    </div>
</template>
