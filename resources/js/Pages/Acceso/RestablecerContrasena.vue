<script setup>
import { useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import IndicadorFortaleza from "@/Components/IndicadorFortaleza.vue";
import Boton from "@/Components/Boton.vue";
import Aviso from "@/Components/Aviso.vue";

const props = defineProps({
    token: { type: String, required: true },
    correo: { type: String, default: "" },
});

const formulario = useForm({
    token: props.token,
    correo: props.correo,
    contrasena: "",
    contrasena_confirmation: "",
});

// Al guardar se entra directamente al sistema: el redireccionamiento lo hace el
// servidor (documento 3, sección 5.1).
function enviar() {
    formulario.post("/acceso/restablecer", {
        onFinish: () => formulario.reset("contrasena", "contrasena_confirmation"),
    });
}
</script>

<template>
    <PanelDeAcceso
        titulo="Definir una contraseña nueva"
        descripcion="Debe tener al menos ocho caracteres, con letras y números."
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
                solo-lectura
            />

            <div>
                <CampoTexto
                    v-model="formulario.contrasena"
                    etiqueta="Contraseña nueva"
                    tipo="password"
                    autocompletar="new-password"
                    :error="formulario.errors.contrasena"
                    autoenfoque
                />
                <IndicadorFortaleza :contrasena="formulario.contrasena" />
            </div>

            <CampoTexto
                v-model="formulario.contrasena_confirmation"
                etiqueta="Repite la contraseña"
                tipo="password"
                autocompletar="new-password"
            />

            <Boton :enviando="formulario.processing" ancho>
                {{ formulario.processing ? "Guardando…" : "Guardar y entrar" }}
            </Boton>
        </form>
    </PanelDeAcceso>
</template>
