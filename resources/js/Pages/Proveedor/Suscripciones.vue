<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import DistribucionLista from "@/Components/DistribucionLista.vue";
import Etiqueta from "@/Components/Etiqueta.vue";
import Modal from "@/Components/Modal.vue";
import Selector from "@/Components/Selector.vue";
import TablaDatos from "@/Components/TablaDatos.vue";

/*
 * Suscripciones (documento 4, capítulo 6, y la pantalla del prototipo).
 *
 * Es la pantalla del negocio entero: el ingreso recurrente mensual arriba, el
 * aviso de quien está en mora, la tabla de todas las suscripciones y las tres
 * tarjetas del prototipo —planes, distribución por plan y la escalera de mora—.
 *
 * De aquí salen solo las acciones que son de todos los clientes a la vez: el
 * catálogo de planes, y abrirle suscripción a una empresa que no tiene ninguna.
 * Las de un cliente concreto —pagar, cambiar de plan, suspender, reactivar—
 * están en su ficha, que es lo que hace el propio prototipo: la única acción de
 * su tabla es «ver historial».
 *
 * Los números de la escalera y el aviso de mora llegan escritos del servidor.
 * Aquí no se resta ninguna fecha ni se compara ningún umbral.
 */
const props = defineProps({
    ingresoRecurrente: { type: String, required: true },
    aviso: { type: String, default: null },
    suscripciones: { type: Array, default: () => [] },
    planes: { type: Array, default: () => [] },
    distribucion: { type: Array, default: () => [] },
    escalera: { type: Array, default: () => [] },
    empresasSinSuscripcion: { type: Array, default: () => [] },
    mediosDePago: { type: Array, default: () => [] },
    planesParaElegir: { type: Array, default: () => [] },
    sinPlanes: { type: String, required: true },
    avisoDeLaSuspension: { type: String, required: true },
});

const RUTA = "/proveedor/suscripciones";

const modal = ref(null);

// El plan que se está editando. Nulo significa que se está creando uno nuevo:
// es el mismo modal para las dos acciones, como en los catálogos del capítulo 8.
const planEnEdicion = ref(null);

const formularioDePlan = useForm({
    nombre: "",
    precio_mensual: "",
    empleados_incluidos: "",
    usuarios_incluidos: "",
});

const formularioDeSuscripcion = useForm({
    tenant_id: "",
    plan_id: "",
    inicia_el: "",
    proxima_fecha_cobro: "",
    medio_de_pago: "",
});

function abrirPlanNuevo() {
    planEnEdicion.value = null;
    formularioDePlan.reset();
    formularioDePlan.clearErrors();
    modal.value = "plan";
}

function abrirPlan(plan) {
    planEnEdicion.value = plan;
    formularioDePlan.nombre = plan.nombre;
    formularioDePlan.precio_mensual = plan.precioMensual;
    formularioDePlan.empleados_incluidos = String(plan.empleadosIncluidos);
    formularioDePlan.usuarios_incluidos = String(plan.usuariosIncluidos);
    formularioDePlan.clearErrors();
    modal.value = "plan";
}

function cerrarModal() {
    modal.value = null;
}

function guardarPlan() {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            formularioDePlan.reset();
            cerrarModal();
        },
    };

    if (planEnEdicion.value) {
        formularioDePlan.put(
            `${RUTA}/planes/${planEnEdicion.value.id}`,
            opciones,
        );
        return;
    }

    formularioDePlan.post(`${RUTA}/planes`, opciones);
}

const alternandoPlan = ref(null);

function alternarPlan(plan) {
    alternandoPlan.value = plan.id;
    router.post(`${RUTA}/planes/${plan.id}/estado`, {}, {
        preserveScroll: true,
        onFinish: () => (alternandoPlan.value = null),
    });
}

function registrarSuscripcion() {
    formularioDeSuscripcion.post(RUTA, {
        preserveScroll: true,
        onSuccess: () => {
            formularioDeSuscripcion.reset();
            cerrarModal();
        },
    });
}

const columnas = [
    { clave: "empresa", titulo: "Empresa" },
    { clave: "plan", titulo: "Plan" },
    { clave: "valorMensual", titulo: "Valor mensual", numerica: true },
    { clave: "proximoCobro", titulo: "Próximo cobro" },
    { clave: "medioDePago", titulo: "Medio de pago" },
    { clave: "cobro", titulo: "Pago" },
    { clave: "estado", titulo: "Estado" },
    { clave: "acciones", titulo: "" },
];

const hayPlanes = computed(() => props.planes.length > 0);

const puedeAbrirSuscripcion = computed(
    () => props.planesParaElegir.length > 0 && props.empresasSinSuscripcion.length > 0,
);
</script>

<template>
    <Head title="Suscripciones" />

    <ArmazonProveedor>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-titulo font-semibold text-texto">
                    Suscripciones
                </h1>
                <p class="mt-1 text-apoyo text-texto-secundario">
                    {{ ingresoRecurrente }} de ingreso recurrente mensual
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Boton
                    v-if="puedeAbrirSuscripcion"
                    tipo="button"
                    variante="secundario"
                    @click="modal = 'suscripcion'"
                >
                    Abrir suscripción
                </Boton>
                <Boton tipo="button" @click="abrirPlanNuevo">
                    + Agregar plan
                </Boton>
            </div>
        </div>

        <Aviso v-if="aviso" tono="error" class="mt-4">{{ aviso }}</Aviso>

        <div class="mt-5">
            <TablaDatos
                :columnas="columnas"
                :filas="suscripciones"
                :clicable="false"
                titulo-vacio="Todavía no hay ninguna suscripción"
                descripcion-vacio="Cuando exista al menos un plan en el catálogo, se le abre suscripción a cada empresa cliente y aquí se ve lo que se le cobra."
            >
                <template #celda-empresa="{ fila }">
                    <span class="font-semibold">{{ fila.empresa }}</span>
                </template>

                <template #celda-cobro="{ fila }">
                    <Etiqueta :tono="fila.cobroTono">{{ fila.cobro }}</Etiqueta>
                </template>

                <template #celda-estado="{ fila }">
                    <Etiqueta :tono="fila.estadoTono">
                        {{ fila.estado }}
                    </Etiqueta>
                </template>

                <template #celda-acciones="{ fila }">
                    <Link
                        :href="`/proveedor/empresas/${fila.empresaId}`"
                        class="text-apoyo font-medium text-accion hover:underline"
                    >
                        Ver historial
                    </Link>
                </template>
            </TablaDatos>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <section
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
            >
                <h2 class="text-seccion font-semibold text-texto">Planes</h2>

                <p v-if="!hayPlanes" class="mt-2 text-apoyo text-texto-secundario">
                    {{ sinPlanes }}
                </p>

                <ul v-else class="mt-3 space-y-3">
                    <li
                        v-for="plan in planes"
                        :key="plan.id"
                        class="border-b border-borde pb-3 last:border-0 last:pb-0"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <span class="text-cuerpo font-semibold text-texto">
                                {{ plan.nombre }}
                            </span>
                            <span class="text-cuerpo font-semibold text-texto">
                                {{ plan.precio }}
                            </span>
                        </div>
                        <p class="text-apoyo text-texto-secundario">
                            {{ plan.cupos }}
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <Etiqueta :tono="plan.tono">
                                {{ plan.estado }}
                            </Etiqueta>
                            <Boton
                                tipo="button"
                                variante="texto"
                                @click="abrirPlan(plan)"
                            >
                                Corregir
                            </Boton>
                            <Boton
                                tipo="button"
                                variante="texto"
                                :enviando="alternandoPlan === plan.id"
                                @click="alternarPlan(plan)"
                            >
                                {{ plan.activo ? "Desactivar" : "Activar" }}
                            </Boton>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
            >
                <h2 class="mb-3 text-seccion font-semibold text-texto">
                    Distribución por plan
                </h2>

                <DistribucionLista
                    :items="distribucion"
                    vacio="Sin planes contratados todavía."
                />
            </section>

            <section
                class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-4"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Escalera de mora
                </h2>

                <ul class="mt-3 space-y-2">
                    <li
                        v-for="peldano in escalera"
                        :key="peldano.etiqueta"
                        class="flex items-baseline justify-between gap-4 text-apoyo text-texto"
                    >
                        <span>{{ peldano.etiqueta }}</span>
                        <span class="tabular-nums">{{ peldano.texto }}</span>
                    </li>
                </ul>

                <p class="mt-3 text-apoyo text-texto-terciario">
                    {{ avisoDeLaSuspension }}
                </p>
                <p class="mt-2 text-apoyo text-texto-terciario">
                    Llegar al último peldaño no suspende a nadie solo: el corte
                    lo ejecuta una persona desde la ficha del cliente, con su
                    motivo escrito.
                </p>
            </section>
        </div>

        <Modal
            :abierto="modal === 'plan'"
            :titulo="planEnEdicion ? 'Corregir el plan' : 'Agregar un plan'"
            @cerrar="cerrarModal"
        >
            <div class="grid gap-4">
                <CampoTexto
                    v-model="formularioDePlan.nombre"
                    etiqueta="Nombre del plan"
                    :error="formularioDePlan.errors.nombre"
                />
                <CampoTexto
                    v-model="formularioDePlan.precio_mensual"
                    tipo="number"
                    etiqueta="Valor mensual"
                    ayuda="Sin sugerencia: la tabla de precios se calcula desde el costo por cliente."
                    :error="formularioDePlan.errors.precio_mensual"
                />
                <CampoTexto
                    v-model="formularioDePlan.empleados_incluidos"
                    tipo="number"
                    etiqueta="Empleados incluidos"
                    :error="formularioDePlan.errors.empleados_incluidos"
                />
                <CampoTexto
                    v-model="formularioDePlan.usuarios_incluidos"
                    tipo="number"
                    etiqueta="Usuarios administradores incluidos"
                    :error="formularioDePlan.errors.usuarios_incluidos"
                />
            </div>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="cerrarModal">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="formularioDePlan.processing"
                    @click="guardarPlan"
                >
                    Guardar plan
                </Boton>
            </template>
        </Modal>

        <Modal
            :abierto="modal === 'suscripcion'"
            titulo="Abrir una suscripción"
            @cerrar="cerrarModal"
        >
            <div class="grid gap-4">
                <Selector
                    v-model="formularioDeSuscripcion.tenant_id"
                    etiqueta="Empresa"
                    :opciones="empresasSinSuscripcion"
                    :error="formularioDeSuscripcion.errors.tenant_id"
                />
                <Selector
                    v-model="formularioDeSuscripcion.plan_id"
                    etiqueta="Plan contratado"
                    :opciones="planesParaElegir"
                    :error="formularioDeSuscripcion.errors.plan_id"
                />
                <CampoFecha
                    v-model="formularioDeSuscripcion.inicia_el"
                    etiqueta="Cliente de pago desde"
                    :error="formularioDeSuscripcion.errors.inicia_el"
                />
                <CampoFecha
                    v-model="formularioDeSuscripcion.proxima_fecha_cobro"
                    etiqueta="Próximo cobro"
                    ayuda="No se deduce de la fecha anterior: hay clientes que arrancan con un mes de cortesía."
                    :error="formularioDeSuscripcion.errors.proxima_fecha_cobro"
                />
                <Selector
                    v-model="formularioDeSuscripcion.medio_de_pago"
                    etiqueta="Medio de pago"
                    :opciones="mediosDePago"
                    :error="formularioDeSuscripcion.errors.medio_de_pago"
                />
            </div>

            <template #pie>
                <Boton variante="texto" tipo="button" @click="cerrarModal">
                    Cancelar
                </Boton>
                <Boton
                    tipo="button"
                    :enviando="formularioDeSuscripcion.processing"
                    @click="registrarSuscripcion"
                >
                    Abrir suscripción
                </Boton>
            </template>
        </Modal>
    </ArmazonProveedor>
</template>
