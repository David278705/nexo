<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Boton from "@/Components/Boton.vue";
import Aviso from "@/Components/Aviso.vue";

const formulario = useForm({ correo: "" });

function enviar() {
    formulario.post("/acceso/recuperar", { preserveScroll: true });
}
</script>

<template>
    <PanelDeAcceso
        titulo="Recuperar contraseña"
        descripcion="Escribe tu correo y te enviamos un enlace para definir una contraseña nueva. El enlace vence en una hora."
    >
        <Aviso v-if="formulario.errors.correo" tono="error" class="mb-4">
            {{ formulario.errors.correo }}
        </Aviso>

        <form class="space-y-4" @submit.prevent="enviar">
            <CampoTexto
                v-model="formulario.correo"
                etiqueta="Correo"
                tipo="email"
                autocompletar="username"
                autoenfoque
            />

            <Boton :enviando="formulario.processing" ancho>
                {{ formulario.processing ? "Enviando…" : "Enviar instrucciones" }}
            </Boton>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link href="/acceso" class="text-accion hover:underline">
                Volver a iniciar sesión
            </Link>
        </p>
    </PanelDeAcceso>
</template>
