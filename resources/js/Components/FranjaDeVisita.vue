<script setup>
import { router } from "@inertiajs/vue3";
import Boton from "@/Components/Boton.vue";

/*
 * La franja permanente de «estás dentro del entorno de un cliente» (documento
 * 4, 4.4).
 *
 * Se pinta encima de todo, en todas las pantallas del entorno del cliente y
 * solo mientras hay una visita abierta: quien está mirando datos de los
 * empleados de otra empresa tiene que verlo sin buscarlo.
 *
 * Dice tres cosas y las tres importan: de quién son los datos, en qué modo se
 * entró —consulta o edición— y por dónde se sale. El motivo escrito al entrar
 * viaja también, porque es lo que el cliente va a leer en su auditoría y quien
 * entró debería estar viendo lo mismo que él verá.
 *
 * El servidor manda el texto entero. Este componente no decide nada: si la prop
 * `visita` es nula —toda petición normal—, no existe.
 */
defineProps({
    visita: { type: Object, required: true },
});

// Salir es un POST por lo mismo que cerrar sesión: un GET que cierra la visita
// lo dispara cualquier cosa que precargue direcciones.
function salir() {
    router.post("/entorno/salir");
}
</script>

<template>
    <div
        class="border-b px-4 py-2 lg:px-6"
        :class="
            visita.tono === 'advertencia'
                ? 'border-advertencia-borde bg-advertencia-fondo text-advertencia-texto'
                : 'border-informacion-borde bg-informacion-fondo text-informacion-texto'
        "
        role="status"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
            <div class="min-w-0">
                <p class="text-apoyo font-semibold">
                    {{ visita.empresa }} · {{ visita.modo }}
                </p>
                <p class="text-apoyo">{{ visita.explicacion }}</p>
                <p class="text-apoyo">Motivo: {{ visita.motivo }}</p>
            </div>

            <Boton tipo="button" variante="secundario" @click="salir">
                {{ visita.salir }}
            </Boton>
        </div>
    </div>
</template>
