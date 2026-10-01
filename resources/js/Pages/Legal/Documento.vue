<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import Aviso from "@/Components/Aviso.vue";

/*
 * Un documento legal, para leerlo. Público: lo enlazan la pantalla de
 * aceptación y el sitio web.
 *
 * El HTML viene del servidor, que lo arma desde el Markdown de
 * `resources/legal/` quitándole cualquier HTML crudo y los enlaces inseguros
 * (`DocumentoLegal::html()`). Por eso aquí se puede pintar con `v-html`.
 */
defineProps({
    titulo: { type: String, required: true },
    version: { type: String, required: true },
    esBorrador: { type: Boolean, required: true },
    html: { type: String, required: true },
});

const pagina = usePage();
</script>

<template>
    <Head :title="titulo" />

    <main class="min-h-screen bg-fondo px-4 py-10">
        <article class="mx-auto w-full max-w-3xl">
            <p class="mb-6 text-lg font-semibold text-marca">
                {{ pagina.props.nombreAplicacion }}
            </p>

            <div
                class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-6 shadow-sm sm:p-10"
            >
                <h1 class="text-2xl font-semibold text-texto">{{ titulo }}</h1>
                <p class="mt-1 text-sm text-texto-terciario">Versión {{ version }}</p>

                <Aviso v-if="esBorrador" tono="advertencia" class="mt-4">
                    Este documento es un borrador y todavía no es el texto definitivo.
                </Aviso>

                <div
                    class="mt-6 text-sm leading-relaxed text-texto-secundario [&_a]:text-accion [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-borde [&_blockquote]:pl-4 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-texto [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-texto [&_li]:mt-1 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-3 [&_strong]:text-texto [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6"
                    v-html="html"
                />
            </div>
        </article>
    </main>
</template>
