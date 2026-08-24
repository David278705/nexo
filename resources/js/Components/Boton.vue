<script setup>
import { computed } from "vue";

const props = defineProps({
    tipo: { type: String, default: "submit" },
    // Las cuatro variantes del documento 3, sección 3.4. El destructivo solo
    // vive dentro de una confirmación, nunca suelto en una tabla.
    variante: {
        type: String,
        default: "primario",
        validator: (valor) =>
            ["primario", "secundario", "texto", "destructivo"].includes(valor),
    },
    // El botón se deshabilita mientras envía, para evitar el doble registro
    // (documento 3, sección 6.2).
    enviando: { type: Boolean, default: false },
    // Para la acción que no se puede ejecutar todavía, con el motivo visible al
    // lado: un botón que se puede pulsar y luego falla no es una validación.
    deshabilitado: { type: Boolean, default: false },
    ancho: { type: Boolean, default: false },
});

const estilos = computed(
    () =>
        ({
            primario:
                "bg-accion text-texto-invertido hover:bg-accion-oscuro border-transparent",
            secundario:
                "bg-superficie text-texto border-borde hover:bg-superficie-resaltada",
            texto: "bg-transparent text-texto-secundario hover:text-texto border-transparent",
            destructivo:
                "bg-error text-texto-invertido hover:bg-error-texto border-transparent",
        })[props.variante],
);
</script>

<template>
    <button
        :type="tipo"
        :disabled="enviando || deshabilitado"
        :aria-busy="enviando ? 'true' : undefined"
        class="inline-flex h-control items-center justify-center gap-2 rounded-[var(--radius-caja)] border px-4 text-tabla font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-45"
        :class="[estilos, ancho ? 'w-full' : '']"
    >
        <!--
            Documento 3, sección 2.5: «Pantalla congelada» está mal, «indicador
            de carga» está bien. Deshabilitar y atenuar el botón no basta —se ve
            igual que un botón roto—, así que mientras envía gira este círculo
            junto al texto, que se queda puesto: quien envía sigue viendo qué
            acción está en curso.
        -->
        <span
            v-if="enviando"
            aria-hidden="true"
            class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
        />
        <slot />
    </button>
</template>
