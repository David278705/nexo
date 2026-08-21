<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Boton from "@/Components/Boton.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";

/*
 * El resumen de lo que quedó configurado (documento 4, sección 4.2).
 *
 * El documento lo pide con nombre propio: «al terminar, el panel debe mostrar un
 * resumen de lo que quedó configurado y ofrecer copiarlo o imprimirlo. Es lo que
 * se le lee al cliente en voz alta antes de cerrar la reunión, y evita la
 * discusión posterior de "yo nunca dije que el sábado fuera laboral"».
 *
 * Tres decisiones de esta pantalla:
 *
 * - **Los renglones los arma el servidor**, leyendo la empresa ya guardada y no
 *   lo que se digitó. Lo que se lee en voz alta tiene que ser lo que quedó en la
 *   base de datos, que es de lo que se va a discutir dentro de seis meses.
 * - **El texto que se copia es el mismo**, y también viene del servidor: dos
 *   versiones del acta de una reunión se separan solas.
 * - **Tiene dirección propia**, así que se puede volver a abrir. Es lo que hace
 *   falta cuando el cliente pide «mándame eso por escrito» dos días después.
 */
defineProps({
    empresa: { type: Object, required: true },
    titulo: { type: String, required: true },
    renglones: { type: Array, required: true },
    texto: { type: String, required: true },
    // `{ url }` si el certificado quedó adjunto; nulo si está pendiente.
    certificado: { type: Object, default: null },
    volver: { type: String, required: true },
});

const copiado = ref(false);

async function copiar(texto) {
    try {
        await navigator.clipboard.writeText(texto);
        copiado.value = true;
    } catch {
        // Sin permiso de portapapeles queda el texto a la vista, que se
        // selecciona a mano, y la impresión, que no depende del navegador.
        copiado.value = false;
    }
}

function imprimir() {
    window.print();
}
</script>

<template>
    <Head :title="`${empresa.nombre} · Resumen`" />

    <ArmazonProveedor>
        <Link :href="volver" class="text-apoyo text-accion">
            ← Volver a empresas
        </Link>

        <h1 class="mt-2 text-titulo font-semibold text-texto">
            {{ empresa.nombre }}
        </h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            {{ titulo }}. Léeselo al cliente antes de cerrar la reunión.
        </p>

        <div class="mt-4">
            <ListaDeDatos :campos="renglones" />
        </div>

        <p v-if="certificado" class="mt-3 text-apoyo">
            <a :href="certificado.url" class="text-accion">
                Descargar el certificado adjunto
            </a>
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-3 print:hidden">
            <Boton tipo="button" @click="copiar(texto)">Copiar el resumen</Boton>

            <Boton tipo="button" variante="secundario" @click="imprimir">
                Imprimir
            </Boton>

            <p v-if="copiado" role="status" class="text-apoyo text-exito">
                Copiado al portapapeles.
            </p>
        </div>

        <!--
            El mismo texto que se copia, a la vista. No es un duplicado
            decorativo: es lo que queda cuando el navegador no da permiso de
            portapapeles, y es lo que se imprime.
        -->
        <pre
            class="mt-4 whitespace-pre-wrap rounded-[var(--radius-caja)] border border-borde bg-superficie p-4 text-tabla text-texto-secundario"
            >{{ texto }}</pre
        >
    </ArmazonProveedor>
</template>
