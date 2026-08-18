<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import Armazon from "@/Components/Armazon.vue";

/*
 * El saludo y la fecha del documento 3, sección 5.2. Los bloques de acción e
 * indicadores que van debajo llegan en F4-03 y F4-04; hasta entonces esta
 * pantalla existe para ser el destino de «Inicio» en el menú.
 *
 * La fecha llega ya escrita desde el servidor, en la zona horaria de la
 * aplicación: formatearla en el navegador la ataría al reloj de cada máquina.
 */
defineProps({
    fecha: { type: String, required: true },
});

const nombre = computed(() => usePage().props.usuario?.nombre ?? "");

// Solo el nombre de pila. «Buenos días, María Fernanda Gutiérrez Ríos» no
// saluda a nadie.
const nombreDePila = computed(() => nombre.value.trim().split(/\s+/)[0] ?? "");
</script>

<template>
    <Head title="Inicio" />

    <Armazon>
        <h1 class="text-titulo font-semibold text-texto">
            Hola, {{ nombreDePila }}
        </h1>
        <p class="mt-1 text-apoyo text-texto-secundario">{{ fecha }}</p>
    </Armazon>
</template>
