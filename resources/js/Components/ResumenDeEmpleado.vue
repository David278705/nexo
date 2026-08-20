<script setup>
/*
 * La franja de datos que va bajo el nombre en la ficha del empleado
 * (documento 3, sección 5.3, bloque `.ficha` del prototipo): documento, fecha
 * de ingreso, antigüedad, tipo de contrato, salario y saldo de vacaciones.
 *
 * Es un componente aparte porque este mismo resumen encabeza el modal grande de
 * registro de novedades (documento 3, 5.5), donde quien registra una
 * incapacidad necesita ver a quién se la está registrando.
 *
 * Los datos llegan armados del servidor. Aquí no se formatea ni se calcula
 * nada: el saldo de vacaciones, en particular, **no se calcula en el navegador
 * jamás** (regla 2 del proyecto).
 */
defineProps({
    // Cada dato es `{ etiqueta, valor, pendiente, nota }`.
    datos: { type: Array, required: true },
});
</script>

<template>
    <div
        class="rounded-[var(--radius-caja)] border border-informacion-borde bg-informacion-fondo px-4 py-3"
    >
        <dl class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <div
                v-for="dato in datos"
                :key="dato.etiqueta"
                :class="dato.nota ? 'col-span-2' : ''"
            >
                <dt
                    class="text-apoyo tracking-wide text-texto-terciario uppercase"
                >
                    {{ dato.etiqueta }}
                </dt>
                <dd
                    class="mt-0.5 text-cuerpo font-semibold"
                    :class="
                        dato.pendiente ? 'text-texto-secundario' : 'text-texto'
                    "
                >
                    {{ dato.valor }}
                </dd>
                <p
                    v-if="dato.nota"
                    class="mt-1 text-apoyo font-normal text-texto-secundario"
                >
                    {{ dato.nota }}
                </p>
            </div>
        </dl>
    </div>
</template>
