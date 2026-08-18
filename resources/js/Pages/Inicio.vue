<script setup>
import { Head, router } from "@inertiajs/vue3";

defineProps({
    mensaje: {
        type: String,
        required: true,
    },
});

// Cerrar sesión es un POST, no un enlace: un GET que cierra la sesión lo
// dispara cualquier cosa que precargue direcciones.
function salir() {
    router.post("/salir");
}
</script>

<template>
    <Head title="Inicio" />

    <main
        class="flex min-h-screen flex-col items-center justify-center gap-6 bg-fondo"
    >
        <h1 class="text-3xl font-semibold text-texto">{{ mensaje }}</h1>

        <button
            type="button"
            class="text-sm text-accion hover:underline focus:outline-none focus:ring-2 focus:ring-accion"
            @click="salir"
        >
            Cerrar sesión
        </button>
    </main>
</template>
