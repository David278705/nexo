<script setup>
import { Head, Link } from "@inertiajs/vue3";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";

/*
 * La página que Inertia muestra en vez de la de depuración de Laravel para
 * un 403 o un 500 (documento 3, 6.3): quien la ve no puede leer una traza,
 * así que no debe encontrarse con una. Sin armazón, a propósito: puede
 * llegar antes de que el usuario o la empresa queden resueltos en la
 * petición, que es justo el momento en que más falta hace no depender de
 * ninguna otra prop.
 */
defineProps({
    status: { type: Number, required: true },
    mensaje: { type: String, required: true },
});
</script>

<template>
    <Head :title="`Error ${status}`" />

    <main
        class="flex min-h-screen items-center justify-center bg-fondo px-4 py-10"
    >
        <div class="w-full max-w-md">
            <div
                class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-6 shadow-sm sm:p-8"
            >
                <Aviso tono="error">{{ mensaje }}</Aviso>

                <div class="mt-6">
                    <Link href="/">
                        <Boton tipo="button" ancho>Volver al inicio</Boton>
                    </Link>
                </div>
            </div>
        </div>
    </main>
</template>
