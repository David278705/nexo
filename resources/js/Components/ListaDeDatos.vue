<script setup>
import EstadoVacio from "@/Components/EstadoVacio.vue";

/*
 * Un bloque de datos de solo lectura: etiqueta a la izquierda, valor a la
 * derecha (documento 3, sección 5.3, y la tarjeta `lista-dist` del prototipo).
 *
 * Lo usa la ficha del empleado en sus cuatro bloques y lo usará el detalle del
 * contrato, que enseña exactamente la misma clase de tarjeta. Escribir el
 * mismo `div` de pares clave-valor por segunda vez es la señal de que este
 * componente tenía que existir.
 *
 * Un bloque puede estar vacío a propósito —el perfil sociodemográfico sin
 * diligenciar—, y entonces se explica con una frase en vez de dejar la tarjeta
 * en blanco.
 */
defineProps({
    titulo: { type: String, default: "" },
    // Cada campo es `{ etiqueta, valor }`.
    campos: { type: Array, default: () => [] },
    vacio: { type: String, default: "" },
});
</script>

<template>
    <section
        class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
    >
        <h3 v-if="titulo" class="text-seccion font-semibold text-texto">
            {{ titulo }}
        </h3>

        <EstadoVacio
            v-if="campos.length === 0"
            titulo="Sin diligenciar"
            :descripcion="vacio"
        />

        <dl v-else class="mt-3">
            <div
                v-for="campo in campos"
                :key="campo.etiqueta"
                class="flex items-baseline justify-between gap-4 border-b border-borde py-2 last:border-b-0"
            >
                <dt class="text-apoyo text-texto-secundario">
                    {{ campo.etiqueta }}
                </dt>
                <dd class="text-right text-cuerpo font-semibold text-texto">
                    {{ campo.valor }}
                </dd>
            </div>
        </dl>
    </section>
</template>
