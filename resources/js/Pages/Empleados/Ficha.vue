<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import ListaDeDatos from "@/Components/ListaDeDatos.vue";
import Pestanas from "@/Components/Pestanas.vue";
import ResumenDeEmpleado from "@/Components/ResumenDeEmpleado.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * La ficha del empleado (documento 3, sección 5.3).
 *
 * Arriba, siempre visible, el nombre con su cargo y estado y la franja de seis
 * datos que incluye el saldo de vacaciones. Debajo, las cuatro pestañas.
 *
 * Todo llega armado del servidor en `FichaDeEmpleado`: aquí no se formatea una
 * fecha, no se compone un nombre y, sobre todo, no se calcula un saldo. Cambiar
 * de pestaña no pide nada porque el contenido de las cuatro ya está en las
 * props.
 */
defineProps({
    empleado: { type: Object, required: true },
    resumen: { type: Array, required: true },
    datos: { type: Array, required: true },
    contrato: { type: Object, required: true },
    novedades: { type: Array, default: () => [] },
    movimientos: { type: Array, default: () => [] },
});

const pestanas = [
    { clave: "datos", titulo: "Datos personales" },
    { clave: "contrato", titulo: "Contrato" },
    { clave: "novedades", titulo: "Novedades" },
    { clave: "movimientos", titulo: "Movimientos" },
];

const activa = ref("datos");

const columnasHistorial = [
    { clave: "orden", titulo: "#", numerica: true },
    { clave: "evento", titulo: "Evento" },
    { clave: "contrato", titulo: "Tipo de contrato" },
    { clave: "desde", titulo: "Desde" },
    { clave: "hasta", titulo: "Hasta" },
];

const columnasNovedades = [
    { clave: "clase", titulo: "Tipo" },
    { clave: "desde", titulo: "Desde" },
    { clave: "hasta", titulo: "Hasta" },
    { clave: "dias", titulo: "Días", numerica: true },
    { clave: "detalle", titulo: "Detalle" },
];

const columnasMovimientos = [
    { clave: "fecha", titulo: "Fecha" },
    { clave: "clase", titulo: "Movimiento" },
    { clave: "cargo", titulo: "Cargo" },
    { clave: "salario", titulo: "Salario", numerica: true },
];
</script>

<template>
    <Head :title="empleado.nombre" />

    <Armazon>
        <Link href="/empleados" class="text-apoyo text-accion">
            ← Volver a empleados
        </Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    {{ empleado.nombre }}
                </h1>
                <p
                    class="mt-1 flex items-center gap-2 text-apoyo text-texto-secundario"
                >
                    <span>{{ empleado.cargo }}</span>
                    <Etiqueta :tono="empleado.activo ? 'exito' : 'neutro'">
                        {{ empleado.estado }}
                    </Etiqueta>
                </p>
            </div>
        </div>

        <div class="mt-4">
            <ResumenDeEmpleado :datos="resumen" />
        </div>

        <div class="mt-5">
            <Pestanas v-model="activa" :pestanas="pestanas" />

            <div
                :id="`panel-${activa}`"
                role="tabpanel"
                :aria-labelledby="`pestana-${activa}`"
                class="mt-4"
            >
                <div
                    v-if="activa === 'datos'"
                    class="grid gap-4 lg:grid-cols-2"
                >
                    <ListaDeDatos
                        v-for="bloque in datos"
                        :key="bloque.titulo"
                        :titulo="bloque.titulo"
                        :campos="bloque.campos"
                        :vacio="bloque.vacio"
                    />
                </div>

                <div v-else-if="activa === 'contrato'" class="grid gap-4">
                    <div class="lg:max-w-xl">
                        <ListaDeDatos
                            titulo="Contrato vigente"
                            :campos="contrato.campos"
                        />
                    </div>

                    <div>
                        <h3 class="mb-2 text-seccion font-semibold text-texto">
                            Historial de periodos
                        </h3>
                        <p class="mb-2 text-apoyo text-texto-secundario">
                            Empieza por el contrato inicial. Cada prórroga y
                            cada cambio de modalidad agregan una fila.
                        </p>
                        <TablaDatos
                            :columnas="columnasHistorial"
                            :filas="contrato.historial"
                            :clicable="false"
                            titulo-vacio="Este contrato aún no tiene historial."
                        />
                        <p
                            v-if="contrato.notaDeHistorial"
                            class="mt-2 text-apoyo text-texto-secundario"
                        >
                            {{ contrato.notaDeHistorial }}
                        </p>
                    </div>
                </div>

                <div v-else-if="activa === 'novedades'">
                    <TablaDatos
                        :columnas="columnasNovedades"
                        :filas="novedades"
                        :clicable="false"
                        titulo-vacio="Esta persona no tiene novedades registradas."
                        descripcion-vacio="Aquí verás juntas y en orden cronológico sus incapacidades, vacaciones y licencias, ausencias o suspensiones."
                    />
                    <p
                        v-if="novedades.length > 0"
                        class="mt-2 text-apoyo text-texto-secundario"
                    >
                        De la más reciente a la más antigua. Los días de
                        vacaciones son hábiles; los de incapacidades, licencias,
                        ausencias y suspensiones son corridos.
                    </p>
                </div>

                <TablaDatos
                    v-else
                    :columnas="columnasMovimientos"
                    :filas="movimientos"
                    :clicable="false"
                    titulo-vacio="Esta persona no tiene movimientos registrados."
                    descripcion-vacio="Aquí verás su historial de aumentos de salario y de promociones de cargo."
                />
            </div>
        </div>
    </Armazon>
</template>
