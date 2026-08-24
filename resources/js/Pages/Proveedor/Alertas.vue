<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { TODOS, useListado } from "@/Utilidades/useListado";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import BarraFiltros from "@/Components/BarraFiltros.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Modal from "@/Components/Modal.vue";
import Paginacion from "@/Components/Paginacion.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * La consola de alertas del proveedor (documento 4, capítulo 7).
 *
 * Existe para una conversación muy concreta: un cliente dice que nunca le
 * llegó un aviso, y esto es lo que responde en menos de un minuto a quién y
 * cuándo se le envió. Es la misma idea que `Alertas/Index.vue` del cliente,
 * pero cruzando todas las empresas, con una columna más —«Empresa»— y un filtro
 * más, y con la acción que el cliente no tiene: reenviar.
 */
const props = defineProps({
    alertas: { type: Object, required: true },
    filtros: { type: Object, required: true },
    aplicados: { type: Array, default: () => [] },
    tieneFiltros: { type: Boolean, default: false },
    entregabilidad: { type: Object, required: true },
    opciones: { type: Object, required: true },
});

const columnas = [
    { clave: "empresa", titulo: "Empresa", ordenable: true },
    { clave: "programada", titulo: "Programada", ordenable: true },
    { clave: "enviada", titulo: "Enviada", ordenable: true },
    { clave: "tipo", titulo: "Alerta", ordenable: true },
    { clave: "empleado", titulo: "Sobre" },
    { clave: "destinatarios", titulo: "Destinatarios" },
    { clave: "estado", titulo: "Estado" },
    { clave: "intentos", titulo: "Intentos", numerica: true },
];

const { pedir, ordenar, quitar, limpiar } = useListado({
    ruta: "/proveedor/alertas",
    filtros: () => props.filtros,
    tieneFiltros: () => props.tieneFiltros,
    deLista: ["empresa", "tipo", "estado"],
});

const empresas = computed(() => [
    { valor: TODOS, texto: "Todas las empresas" },
    ...props.opciones.empresas,
]);

const tipos = computed(() => [
    { valor: TODOS, texto: "Todas las alertas" },
    ...props.opciones.tipos,
]);

const estados = computed(() => [
    { valor: TODOS, texto: "Todos los estados" },
    ...props.opciones.estados,
]);

// El detalle es la fila que se pulsó, sin ninguna vuelta al servidor.
const detalle = ref(null);
const reenviando = ref(false);

const datosDelDetalle = computed(() =>
    detalle.value === null
        ? []
        : [
              { etiqueta: "Empresa", valor: detalle.value.empresa },
              { etiqueta: "Alerta", valor: detalle.value.tipo },
              { etiqueta: "Sobre", valor: detalle.value.empleado },
              { etiqueta: "Fecha programada", valor: detalle.value.programada },
              { etiqueta: "Fecha de envío", valor: detalle.value.enviada },
              { etiqueta: "Estado", valor: detalle.value.estado },
              { etiqueta: "Intentos", valor: String(detalle.value.intentos) },
              {
                  etiqueta: "Destinatarios",
                  valor: detalle.value.destinatarios.join(", "),
              },
          ],
);

function reenviar() {
    if (detalle.value === null) {
        return;
    }

    reenviando.value = true;

    router.post(
        `/proveedor/alertas/${detalle.value.empresaId}/${detalle.value.id}/reenviar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                reenviando.value = false;
                detalle.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Alertas" />

    <ArmazonProveedor>
        <h1 class="text-titulo font-semibold text-texto">Alertas enviadas</h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            Existe para cuando un cliente diga que nunca le llegó un aviso.
        </p>

        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <TarjetaIndicador
                :valor="entregabilidad.enviadas"
                etiqueta="Entregadas este mes"
            />
            <TarjetaIndicador
                :valor="entregabilidad.fallidas"
                etiqueta="Fallidas este mes"
                :nota="
                    entregabilidad.fallidas > 0
                        ? 'Revisa el motivo en cada una: casi siempre es un correo mal escrito.'
                        : ''
                "
                :nota-tono="entregabilidad.fallidas > 0 ? 'error' : ''"
            />
            <TarjetaIndicador
                :valor="entregabilidad.porcentaje ?? '—'"
                etiqueta="Entregabilidad del mes"
                nota="«Rebotada» todavía no se puede medir: falta conectar un proveedor de correo transaccional."
            />
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
                        :model-value="filtros.empresa"
                        etiqueta="Empresa"
                        :opciones="empresas"
                        :con-marcador="false"
                        @update:model-value="pedir({ empresa: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[260px]">
                    <Selector
                        :model-value="filtros.tipo"
                        etiqueta="Alerta"
                        :opciones="tipos"
                        :con-marcador="false"
                        @update:model-value="pedir({ tipo: $event, page: 1 })"
                    />
                </div>

                <div class="min-w-[190px]">
                    <Selector
                        :model-value="filtros.estado"
                        etiqueta="Estado"
                        :opciones="estados"
                        :con-marcador="false"
                        @update:model-value="pedir({ estado: $event, page: 1 })"
                    />
                </div>
            </BarraFiltros>

            <TablaDatos
                :columnas="columnas"
                :filas="alertas.filas"
                :orden-por="filtros.orden"
                :orden-direccion="filtros.direccion"
                titulo-vacio="Todavía no se ha enviado ninguna alerta"
                :descripcion-vacio="
                    tieneFiltros
                        ? 'No encontramos envíos con estos filtros.'
                        : 'Cuando el proceso diario tenga algo que avisar, aparecerá aquí, de cualquier empresa.'
                "
                @ordenar="ordenar"
                @abrir="detalle = $event"
            >
                <template #celda-empresa="{ fila }">
                    <span class="font-semibold">{{ fila.empresa }}</span>
                </template>

                <template #celda-destinatarios="{ fila }">
                    {{ fila.destinatarios.join(", ") }}
                </template>

                <template #celda-estado="{ fila }">
                    <Etiqueta :tono="fila.tono">{{ fila.estado }}</Etiqueta>
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
                v-if="alertas.total > 0"
                :pagina="alertas.pagina"
                :paginas="alertas.paginas"
                :total="alertas.total"
                :desde="alertas.desde"
                :hasta="alertas.hasta"
                :por-pagina="filtros.porPagina"
                :tamanos="opciones.tamanos"
                @ir="pedir({ page: $event })"
                @tamano="pedir({ porPagina: $event, page: 1 })"
            />
        </div>

        <Modal
            :abierto="detalle !== null"
            :titulo="detalle?.asunto ?? ''"
            ancho="ancho"
            @cerrar="detalle = null"
        >
            <template v-if="detalle !== null">
                <p
                    v-for="(parrafo, indice) in detalle.parrafos"
                    :key="indice"
                    class="mb-2 text-cuerpo text-texto"
                >
                    {{ parrafo }}
                </p>

                <Aviso v-if="detalle.motivo !== ''" tono="error" class="mt-4">
                    No se pudo entregar: {{ detalle.motivo }}
                </Aviso>

                <div class="mt-5">
                    <ListaDeDatos titulo="Envío" :campos="datosDelDetalle" />
                </div>
            </template>

            <template #pie>
                <Boton
                    tipo="button"
                    variante="secundario"
                    @click="detalle = null"
                >
                    Cerrar
                </Boton>
                <Boton
                    v-if="detalle?.puedeReenviarse"
                    tipo="button"
                    :enviando="reenviando"
                    @click="reenviar"
                >
                    Reenviar
                </Boton>
            </template>
        </Modal>
    </ArmazonProveedor>
</template>
