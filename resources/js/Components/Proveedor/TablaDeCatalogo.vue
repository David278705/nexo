<script setup>
import Boton from "@/Components/Boton.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * La tabla de una lista administrable del panel: código, nombre, estado y las
 * dos acciones.
 *
 * Existe porque las entidades —EPS, fondos, ARL, cajas y bancos— y los
 * municipios se administran exactamente igual: se agregan, se corrigen y se
 * desactivan, y ninguno se borra. Escribir dos veces la misma tabla habría
 * separado el día en que una de las dos cambie.
 *
 * Aquí se enseña también lo desactivado, a propósito: esta es la pantalla desde
 * la que se vuelve a activar, y lo que desaparece de la vista no se puede
 * recuperar.
 */
defineProps({
    filas: { type: Array, required: true },
    // Cómo se llama la columna del código en esta lista: el de la Supersalud
    // para las EPS, el del DANE para los municipios.
    tituloCodigo: { type: String, required: true },
    tituloVacio: { type: String, required: true },
    descripcionVacio: { type: String, default: "" },
});

const emit = defineEmits(["editar", "alternar"]);

const columnas = [
    { clave: "codigo", titulo: "Código" },
    { clave: "nombre", titulo: "Nombre" },
    { clave: "estado", titulo: "Estado" },
    { clave: "acciones", titulo: "Acciones" },
];
</script>

<template>
    <TablaDatos
        :columnas="
            columnas.map((columna) =>
                columna.clave === 'codigo'
                    ? { ...columna, titulo: tituloCodigo }
                    : columna,
            )
        "
        :filas="filas"
        :clicable="false"
        :titulo-vacio="tituloVacio"
        :descripcion-vacio="descripcionVacio"
    >
        <template #celda-estado="{ fila }">
            <Etiqueta :tono="fila.tono">{{ fila.estado }}</Etiqueta>
        </template>

        <template #celda-acciones="{ fila }">
            <div class="flex flex-wrap gap-2 py-1">
                <Boton
                    tipo="button"
                    variante="secundario"
                    @click="emit('editar', fila)"
                >
                    Editar
                </Boton>
                <Boton
                    tipo="button"
                    variante="texto"
                    @click="emit('alternar', fila)"
                >
                    {{ fila.activo ? "Desactivar" : "Activar" }}
                </Boton>
            </div>
        </template>
    </TablaDatos>
</template>
