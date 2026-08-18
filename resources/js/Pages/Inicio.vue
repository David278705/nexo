<script setup>
import { Head } from "@inertiajs/vue3";

defineProps({
    mensaje: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Inicio" />

    <main class="flex min-h-screen items-center justify-center bg-gray-50">
        <h1 class="text-4xl font-bold text-gray-800">{{ mensaje }}</h1>
    </main>
</template>
