<script setup>
import { Head } from "@inertiajs/vue3";
import Armazon from "@/Components/Armazon.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";

/*
 * El contenido provisional de las secciones del menú mientras llega su tarea de
 * la fase 1 o 2. Existe para que el armazón se pueda recorrer entero desde ya:
 * la ruta, el permiso y el resaltado del menú son los definitivos, lo único que
 * falta es lo de dentro.
 */
defineProps({
    titulo: { type: String, required: true },
});
</script>

<template>
    <Head :title="titulo" />

    <Armazon>
        <h1 class="text-titulo font-semibold text-texto">{{ titulo }}</h1>

        <div
            class="mt-4 rounded-[var(--radius-modal)] border border-borde bg-superficie"
        >
            <EstadoVacio
                titulo="Esta sección todavía no tiene contenido"
                descripcion="El menú, los permisos y la navegación ya funcionan. Lo de dentro llega en su propia tarea."
            />
        </div>
    </Armazon>
</template>
