<script setup>
/*
 * La marca de Nexo (`PROD-26`): el símbolo y, si se pide, el nombre al lado.
 *
 * El símbolo es una «N» blanca sobre el azul de marca, con un nodo en el azul de
 * acción donde se unen sus trazos: nexo es lo que une. Es el mismo dibujo de
 * `public/favicon.svg`, y los colores son los tokens del sistema de diseño, no
 * literales.
 *
 * `invertida` es para fondos oscuros —la barra lateral—: el nombre va en blanco.
 */
defineProps({
    tamano: { type: Number, default: 28 },
    conNombre: { type: Boolean, default: true },
    invertida: { type: Boolean, default: false },
});
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <svg
            :width="tamano"
            :height="tamano"
            viewBox="0 0 64 64"
            role="img"
            aria-label="Nexo"
            class="shrink-0"
        >
            <rect width="64" height="64" rx="14" fill="var(--color-marca)" />
            <path
                d="M19 46V18l26 28V18"
                fill="none"
                stroke="var(--color-texto-invertido)"
                stroke-width="7"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <circle cx="32" cy="32" r="6" fill="var(--color-accion)" stroke="var(--color-texto-invertido)" stroke-width="2.5" />
        </svg>
        <span
            v-if="conNombre"
            class="text-lg font-semibold tracking-tight"
            :class="invertida ? 'text-texto-invertido' : 'text-marca'"
        >
            Nexo
        </span>
    </span>
</template>
