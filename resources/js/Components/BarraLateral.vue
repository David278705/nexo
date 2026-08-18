<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

/*
 * La barra lateral del documento 3, sección 4.1: los módulos a la izquierda y
 * el contenido a la derecha.
 *
 * No decide nada. Los módulos llegan ya filtrados desde el servidor —la misma
 * política que responde 403— así que un módulo sin permiso no aparece aquí
 * porque no llega, no porque este componente lo esconda.
 */
const props = defineProps({
    modulos: { type: Array, required: true },
    urlActual: { type: String, default: "/" },
});

defineEmits(["navegar"]);

/*
 * Los módulos agrupados como en el prototipo, conservando el orden en que
 * llegan. `Inicio` no trae grupo y va suelto arriba.
 */
const grupos = computed(() => {
    const agrupados = [];

    for (const modulo of props.modulos) {
        const ultimo = agrupados[agrupados.length - 1];

        if (ultimo && ultimo.nombre === modulo.grupo) {
            ultimo.modulos.push(modulo);
            continue;
        }

        agrupados.push({ nombre: modulo.grupo ?? null, modulos: [modulo] });
    }

    return agrupados;
});

// La sección activa es la de la dirección actual. Las de la fase 1 tendrán
// rutas hijas —`/empleados/7`—, y la opción del menú sigue siendo la suya.
function estaActivo(modulo) {
    if (modulo.url === "/") {
        return props.urlActual === "/";
    }

    return (
        props.urlActual === modulo.url ||
        props.urlActual.startsWith(`${modulo.url}/`)
    );
}
</script>

<template>
    <div class="flex h-full w-full flex-col bg-marca">
        <div class="border-b border-menu-borde px-[18px] py-5">
            <p class="text-tabla font-semibold text-texto-invertido">
                Gestión de Talento Humano
            </p>
            <p class="mt-0.5 text-apoyo text-menu-apoyo">
                Sistema de acompañamiento laboral
            </p>
        </div>

        <nav class="flex-1 overflow-y-auto p-2" aria-label="Secciones">
            <template v-for="(grupo, indice) in grupos" :key="grupo.nombre ?? indice">
                <p
                    v-if="grupo.nombre"
                    class="px-3 pb-1.5 pt-3.5 text-[10.5px] font-medium uppercase tracking-wider text-menu-grupo"
                >
                    {{ grupo.nombre }}
                </p>

                <Link
                    v-for="modulo in grupo.modulos"
                    :key="modulo.clave"
                    :href="modulo.url"
                    :aria-current="estaActivo(modulo) ? 'page' : undefined"
                    class="mb-px flex items-center gap-2 rounded-[var(--radius-caja)] px-3 py-2 text-tabla transition-colors"
                    :class="
                        estaActivo(modulo)
                            ? 'bg-accion font-medium text-texto-invertido'
                            : 'text-menu-texto hover:bg-menu-resaltado hover:text-texto-invertido'
                    "
                    @click="$emit('navegar')"
                >
                    {{ modulo.etiqueta }}
                </Link>
            </template>
        </nav>
    </div>
</template>
