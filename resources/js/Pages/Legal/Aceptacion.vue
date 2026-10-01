<script setup>
import { router, useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import Boton from "@/Components/Boton.vue";

/*
 * Aceptar los documentos legales a nombre de la empresa.
 *
 * Sin el armazón del menú, igual que la pantalla de suspensión: mientras haya
 * algo pendiente, `ExigirAceptacionLegal` no deja pasar a ninguna otra parte, y
 * un menú cuyos enlaces vuelven todos aquí sería una burla.
 *
 * Cada documento se abre en otra pestaña para leerlo sin perder esta. Solo el
 * superadministrador acepta; los demás ven quién tiene que hacerlo.
 */
const props = defineProps({
    empresa: { type: String, required: true },
    puedeAceptar: { type: Boolean, required: true },
    superadministrador: { type: String, default: null },
    documentos: { type: Array, required: true },
});

const formulario = useForm({ acepto: false });

function aceptar() {
    formulario.post("/aceptacion");
}

function salir() {
    router.post("/salir");
}
</script>

<template>
    <PanelDeAcceso
        titulo="Antes de empezar"
        descripcion="Para usar el sistema, tu empresa tiene que aceptar estos documentos."
    >
        <p class="text-sm font-semibold text-texto">{{ empresa }}</p>

        <ul class="mt-4 space-y-2">
            <li v-for="documento in documentos" :key="documento.clave">
                <a
                    :href="documento.url"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-accion hover:underline"
                >
                    {{ documento.titulo }}
                </a>
                <span class="text-xs text-texto-terciario">
                    · versión {{ documento.version }}
                </span>
            </li>
        </ul>

        <form v-if="puedeAceptar" class="mt-6 grid gap-4" @submit.prevent="aceptar">
            <label class="flex items-start gap-2 text-sm text-texto-secundario">
                <input
                    v-model="formulario.acepto"
                    type="checkbox"
                    class="mt-0.5 rounded border-borde text-accion focus:ring-accion"
                />
                <span>
                    Leí los documentos y los acepto a nombre de
                    <strong class="text-texto">{{ empresa }}</strong>.
                </span>
            </label>

            <p v-if="formulario.errors.acepto" class="text-apoyo text-error">
                {{ formulario.errors.acepto }}
            </p>

            <Boton :enviando="formulario.processing" ancho>
                {{ formulario.processing ? "Registrando…" : "Aceptar y continuar" }}
            </Boton>
        </form>

        <p v-else class="mt-6 text-sm text-texto-secundario">
            Solo el superadministrador de la empresa puede aceptarlos<template
                v-if="superadministrador"
                >: {{ superadministrador }}</template
            >. Cuando lo haga, podrás entrar.
        </p>

        <div class="mt-4">
            <Boton tipo="button" variante="texto" ancho @click="salir">
                Cerrar sesión
            </Boton>
        </div>
    </PanelDeAcceso>
</template>
