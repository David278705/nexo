<script setup>
import { useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Boton from "@/Components/Boton.vue";

/*
 * El código de cada sesión del panel. Se pide una vez por sesión: cerrar sesión
 * lo vuelve a pedir, recargar una pantalla no.
 *
 * El error es uno solo y no distingue «código vencido» de «código equivocado»:
 * lo segundo le diría a quien está probando que va por buen camino.
 */
const formulario = useForm({ codigo: "" });

function enviar() {
    formulario.post("/proveedor/verificacion", {
        onFinish: () => formulario.reset("codigo"),
    });
}
</script>

<template>
    <PanelDeAcceso
        titulo="Escribe tu código"
        descripcion="El de seis dígitos que muestra tu aplicación en este momento."
    >
        <form class="space-y-4" @submit.prevent="enviar">
            <CampoTexto
                v-model="formulario.codigo"
                etiqueta="Código de seis dígitos"
                autocompletar="one-time-code"
                :error="formulario.errors.codigo"
                autoenfoque
            />

            <Boton :enviando="formulario.processing" ancho>
                {{ formulario.processing ? "Comprobando…" : "Entrar al panel" }}
            </Boton>
        </form>
    </PanelDeAcceso>
</template>
