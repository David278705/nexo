<script setup>
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import Armazon from "@/Components/Armazon.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Modal from "@/Components/Modal.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * La pantalla de auditoría (REV-04, documento 1, 8.2 y documento 3, 5.6).
 *
 * De solo lectura, como Alertas: no hay botón de crear, ni columna de
 * acciones, ni edición. Lo que se enseña es lo que el sistema ya hizo.
 *
 * La fila trae su detalle completo desde el servidor —igual que Alertas—, así
 * que abrir el panel lateral no pide nada aparte: los valores anterior y
 * nuevo, lado a lado, ya viajaron con la página.
 */
const props = defineProps({
    auditoria: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    opciones: { type: Object, required: true },
});

const columnas = [
    { clave: "fecha", titulo: "Fecha y hora", ordenable: true },
    { clave: "usuario", titulo: "Usuario" },
    { clave: "modulo", titulo: "Módulo" },
    { clave: "accion", titulo: "Acción" },
    { clave: "registro", titulo: "Registro" },
    { clave: "detalle", titulo: "Detalle" },
];

const { pedir, ordenar, quitar, limpiar } = useListado({
    ruta: "/auditoria",
    filtros: () => props.filtros,
    tieneFiltros: () => props.tieneFiltros,
    deLista: ["usuario", "modulo", "accion"],
});

const detalle = ref(null);
</script>

<template>
    <Head title="Auditoría" />

    <Armazon>
        <div>
            <h1 class="text-titulo font-semibold text-texto">Auditoría</h1>
            <p class="mt-1 text-apoyo text-texto-secundario">
                Toda acción realizada en el sistema. Esta tabla no se puede
                editar ni borrar.
            </p>
        </div>

        <div class="mt-5">
            <BarraFiltros
                :aplicados="aplicados"
                @quitar="quitar"
                @limpiar="limpiar"
            >
                <div class="min-w-[160px]">
                    <CampoFecha
                        :model-value="filtros.desde"
                        etiqueta="Desde"
                        opcional
                        @update:model-value="pedir({ desde: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[160px]">
                    <CampoFecha
                        :model-value="filtros.hasta"
                        etiqueta="Hasta"
                        opcional
                        @update:model-value="pedir({ hasta: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[220px]">
                    <Selector
                        :model-value="filtros.usuario"
                        etiqueta="Usuario"
                        :opciones="[
                            { valor: TODOS, texto: 'Todos los usuarios' },
                            ...opciones.usuarios,
                        ]"
                        :con-marcador="false"
                        @update:model-value="pedir({ usuario: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[220px]">
                    <Selector
                        :model-value="filtros.modulo"
                        etiqueta="Módulo"
                        :opciones="[
                            { valor: TODOS, texto: 'Todos los módulos' },
                            ...opciones.modulos,
                        ]"
                        :con-marcador="false"
                        @update:model-value="pedir({ modulo: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.accion"
                        etiqueta="Acción"
                        :opciones="[
                            { valor: TODOS, texto: 'Todas las acciones' },
                            ...opciones.acciones,
                        ]"
                        :con-marcador="false"
                        @update:model-value="pedir({ accion: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="auditoria.filas"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Todavía no hay nada registrado."
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos movimientos con estos filtros.'
                        : 'Cuando alguien cree, edite o elimine un registro, la línea va a quedar aquí.'
                "
                @ordenar="ordenar"
                @abrir="detalle = $event"
            >
                <template #celda-accion="{ fila }">
                    <Etiqueta :tono="fila.tono">{{ fila.accion }}</Etiqueta>
                </template>

                <template #accion-vacio>
                    <Boton
                        v-if="tieneFiltros"
                        tipo="button"
                        variante="secundario"
                        @click="limpiar"
                    >
                        Quitar filtros
                    </Boton>
                </template>
            </TablaDatos>

            <Paginacion
                v-if="auditoria.total > 0"
                :pagina="auditoria.pagina"
                :paginas="auditoria.paginas"
                :total="auditoria.total"
                :desde="auditoria.desde"
                :hasta="auditoria.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>

        <Modal
            :abierto="detalle !== null"
            :titulo="detalle?.registro ?? ''"
            ancho="ancho"
            @cerrar="detalle = null"
        >
            <template v-if="detalle !== null">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-apoyo">
                    <div>
                        <dt class="text-texto-secundario">Fecha y hora</dt>
                        <dd class="font-semibold text-texto">
                            {{ detalle.fecha }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-texto-secundario">Usuario</dt>
                        <dd class="font-semibold text-texto">
                            {{ detalle.usuario }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-texto-secundario">Módulo</dt>
                        <dd class="font-semibold text-texto">
                            {{ detalle.modulo }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-texto-secundario">Acción</dt>
                        <dd>
                            <Etiqueta :tono="detalle.tono">{{
                                detalle.accion
                            }}</Etiqueta>
                        </dd>
                    </div>
                </dl>

                <div v-if="detalle.cambios.length > 0" class="mt-5">
                    <h3 class="text-seccion font-semibold text-texto">
                        Valores
                    </h3>
                    <table class="mt-2 w-full text-apoyo">
                        <thead>
                            <tr class="text-left text-texto-secundario">
                                <th class="py-1 font-semibold">Campo</th>
                                <th class="py-1 font-semibold">Antes</th>
                                <th class="py-1 font-semibold">Después</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="cambio in detalle.cambios"
                                :key="cambio.campo"
                                class="border-t border-borde"
                            >
                                <td class="py-1.5 capitalize text-texto">
                                    {{ cambio.campo }}
                                </td>
                                <td class="py-1.5 text-texto-secundario">
                                    {{ cambio.antes }}
                                </td>
                                <td class="py-1.5 font-semibold text-texto">
                                    {{ cambio.despues }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="mt-5 text-apoyo text-texto-secundario">
                    {{ detalle.detalle }}
                </p>
            </template>

            <template #pie>
                <Boton
                    tipo="button"
                    variante="secundario"
                    @click="detalle = null"
                >
                    Cerrar
                </Boton>
            </template>
        </Modal>
    </Armazon>
</template>
