<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import Aviso from "@/Components/Aviso.vue";
import Marca from "@/Components/Marca.vue";

defineProps({
    titulo: { type: String, required: true },
    descripcion: { type: String, default: "" },
});

const pagina = usePage();
</script>

<template>
    <Head :title="titulo" />

    <main
        class="flex min-h-screen items-center justify-center bg-fondo px-4 py-10"
    >
        <div class="w-full max-w-md">
            <p class="mb-6 flex justify-center">
                <Marca :tamano="36" />
            </p>

            <div
                class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-6 shadow-sm sm:p-8"
            >
                <h1 class="text-xl font-semibold text-texto">{{ titulo }}</h1>
                <p
                    v-if="descripcion"
                    class="mt-1 text-sm text-texto-secundario"
                >
                    {{ descripcion }}
                </p>

                <Aviso v-if="pagina.props.estado" tono="informacion" class="mt-4">
                    {{ pagina.props.estado }}
                </Aviso>

                <div class="mt-6">
                    <slot />
                </div>
            </div>
        </div>
    </main>
</template>
