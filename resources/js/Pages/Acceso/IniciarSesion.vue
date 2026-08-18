<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Boton from "@/Components/Boton.vue";
import Aviso from "@/Components/Aviso.vue";

const formulario = useForm({
    correo: "",
    contrasena: "",
    recordarme: false,
});

// Un solo mensaje de error para toda la pantalla. El servidor nunca dice si el
// correo existe, así que tampoco se señala un campo concreto: eso ya sería una
// pista (documento 3, sección 5.1).
function enviar() {
    formulario.post("/acceso", {
        onFinish: () => formulario.reset("contrasena"),
    });
}
</script>

<template>
    <PanelDeAcceso titulo="Iniciar sesión">
        <Aviso
            v-if="formulario.errors.correo || formulario.errors.contrasena"
            tono="error"
            class="mb-4"
        >
            {{ formulario.errors.correo || formulario.errors.contrasena }}
        </Aviso>

        <form class="space-y-4" @submit.prevent="enviar">
            <CampoTexto
                v-model="formulario.correo"
                etiqueta="Correo"
                tipo="email"
                autocompletar="username"
                autoenfoque
            />

            <CampoTexto
                v-model="formulario.contrasena"
                etiqueta="Contraseña"
                tipo="password"
                autocompletar="current-password"
            />

            <label class="flex items-center gap-2 text-sm text-texto-secundario">
                <input
                    v-model="formulario.recordarme"
                    type="checkbox"
                    class="rounded border-borde text-accion focus:ring-accion"
                />
                Mantener la sesión iniciada
            </label>

            <Boton :enviando="formulario.processing" ancho>
                {{ formulario.processing ? "Entrando…" : "Entrar" }}
            </Boton>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link href="/acceso/recuperar" class="text-accion hover:underline">
                Olvidé mi contraseña
            </Link>
        </p>
    </PanelDeAcceso>
</template>
