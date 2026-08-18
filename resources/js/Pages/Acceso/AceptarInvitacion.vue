<script setup>
import { useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import IndicadorFortaleza from "@/Components/IndicadorFortaleza.vue";
import Boton from "@/Components/Boton.vue";
import Aviso from "@/Components/Aviso.vue";

const props = defineProps({
    token: { type: String, required: true },
    nombre: { type: String, required: true },
    correo: { type: String, required: true },
    empresa: { type: String, default: null },
    // El resumen de a qué va a poder entrar. Es la primera vez que esta persona
    // lo sabe, y evita la llamada de «entré y no veo vacaciones».
    modulos: { type: Array, default: () => [] },
});

const formulario = useForm({
    contrasena: "",
    contrasena_confirmation: "",
});

function enviar() {
    formulario.post(`/invitacion/${props.token}`, {
        onFinish: () => formulario.reset("contrasena", "contrasena_confirmation"),
    });
}
</script>

<template>
    <PanelDeAcceso
        :titulo="`Hola, ${nombre}`"
        :descripcion="
            empresa
                ? `Define tu contraseña para entrar a ${empresa}.`
                : 'Define tu contraseña para entrar.'
        "
    >
        <div class="mb-6 rounded-[var(--radius-caja)] border border-borde p-4">
            <p class="text-sm font-medium text-texto">Tendrás acceso a:</p>
            <ul
                v-if="modulos.length"
                class="mt-2 space-y-1 text-sm text-texto-secundario"
            >
                <li v-for="modulo in modulos" :key="modulo">{{ modulo }}</li>
            </ul>
            <p v-else class="mt-2 text-sm text-texto-secundario">
                Todavía no te asignaron secciones. Cuando entres verás solo el
                inicio; pídeselas a quien te invitó.
            </p>
        </div>

        <Aviso v-if="formulario.errors.contrasena" tono="error" class="mb-4">
            {{ formulario.errors.contrasena }}
        </Aviso>

        <form class="space-y-4" @submit.prevent="enviar">
            <CampoTexto
                :model-value="correo"
                etiqueta="Correo"
                tipo="email"
                solo-lectura
            />

            <div>
                <CampoTexto
                    v-model="formulario.contrasena"
                    etiqueta="Contraseña"
                    tipo="password"
                    autocompletar="new-password"
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
                {{ formulario.processing ? "Entrando…" : "Definir y entrar" }}
            </Boton>
        </form>
    </PanelDeAcceso>
</template>
