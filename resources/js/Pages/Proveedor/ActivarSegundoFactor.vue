<script setup>
import { useForm } from "@inertiajs/vue3";
import PanelDeAcceso from "@/Components/Acceso/PanelDeAcceso.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Boton from "@/Components/Boton.vue";
import Aviso from "@/Components/Aviso.vue";

/*
 * Activar el segundo factor, obligatorio para todo el equipo del proveedor
 * (documento 4, capítulo 10).
 *
 * No es una pantalla de ajustes que se pueda cerrar: el panel entero desvía aquí
 * hasta que quede activo. El motivo está escrito en el documento y conviene que
 * se lea también aquí, porque una obligación cuyo motivo no se entiende se vive
 * como un estorbo: estas cuentas ven la información de todos los clientes a la
 * vez.
 *
 * La clave se enseña como texto en grupos de cuatro y como enlace `otpauth://`.
 * No hay código QR: dibujarlo pediría una dependencia más para el mismo dato.
 */
defineProps({
    clave: { type: String, required: true },
    enlace: { type: String, required: true },
});

const formulario = useForm({ codigo: "" });

function enviar() {
    formulario.post("/proveedor/segundo-factor", {
        onFinish: () => formulario.reset("codigo"),
    });
}
</script>

<template>
    <PanelDeAcceso
        titulo="Activa el segundo factor"
        descripcion="Es obligatorio para el equipo del proveedor: estas cuentas ven la información de todas las empresas a la vez."
    >
        <ol
            class="space-y-3 text-apoyo text-texto-secundario"
            aria-label="Pasos para activarlo"
        >
            <li>
                1. Abre tu aplicación de códigos —Google Authenticator, Microsoft
                Authenticator, 1Password o la que uses— y agrega una cuenta
                nueva.
            </li>
            <li>
                2. Escribe esta clave, o pega el enlace de abajo si la aplicación
                lo acepta:
                <span
                    class="mt-2 block break-all rounded-[var(--radius-caja)] border border-borde bg-fondo px-3 py-2 font-mono text-tabla tracking-wider text-texto"
                >
                    {{ clave }}
                </span>
                <span
                    class="mt-2 block break-all text-apoyo text-texto-terciario"
                >
                    {{ enlace }}
                </span>
            </li>
            <li>3. Escribe abajo el código de seis dígitos que aparezca.</li>
        </ol>

        <Aviso tono="advertencia" class="mt-5">
            Guarda la clave en tu gestor de contraseñas antes de continuar.
            Después de activarla no se vuelve a enseñar: si pierdes el teléfono,
            hay que emitir una nueva.
        </Aviso>

        <form class="mt-6 space-y-4" @submit.prevent="enviar">
            <CampoTexto
                v-model="formulario.codigo"
                etiqueta="Código de seis dígitos"
                autocompletar="one-time-code"
                :error="formulario.errors.codigo"
                autoenfoque
            />

            <Boton :enviando="formulario.processing" ancho>
                {{ formulario.processing ? "Comprobando…" : "Activar" }}
            </Boton>
        </form>
    </PanelDeAcceso>
</template>
