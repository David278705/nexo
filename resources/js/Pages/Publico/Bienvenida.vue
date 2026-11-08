<script setup>
import { Head, Link } from "@inertiajs/vue3";
import Marca from "@/Components/Marca.vue";

/*
 * La página pública de Nexo (PROD-27): lo primero que ve quien abre el dominio
 * sin sesión.
 *
 * Dice en este orden lo que el cliente compra —que el sistema le avisa antes de
 * que se venza un plazo legal—, qué más hace, cómo se arranca, cuánto cuesta y a
 * dónde escribir. Los precios llegan de la tabla de planes y lo comercial de
 * `config/comercial.php`: aquí no hay un solo número escrito.
 */
defineProps({
    planes: { type: Array, default: () => [] },
    usuarioAdicional: { type: String, default: "" },
    correo: { type: String, default: "" },
    documentos: { type: Array, default: () => [] },
    conSesion: { type: Boolean, default: false },
});

const avisos = [
    {
        titulo: "Periodos de prueba",
        texto: "Un mes cumplido, quince días antes y la víspera de que termine, en indefinido, término fijo y obra o labor.",
    },
    {
        titulo: "Contratos a término fijo",
        texto: "El plazo para avisar la no renovación, el día del preaviso, la última prórroga corta y la cercanía al tope de cuatro años.",
    },
    {
        titulo: "Vacaciones",
        texto: "Quién acumuló un periodo completo y quién cumple un año sin el disfrute mínimo que exige la ley.",
    },
    {
        titulo: "Obra o labor",
        texto: "Cada dos meses, la lista de contratos de obra o labor activos, para que ninguno mute a indefinido sin que nadie lo note.",
    },
];

const modulos = [
    "Empleados con su ficha completa",
    "Contratos, prórrogas y cambio a indefinido",
    "Vacaciones con el saldo calculado, nunca digitado",
    "Incapacidades, licencias, ausencias y suspensiones",
    "Aumentos de salario, cambios de cargo y terminaciones",
    "Indicadores de planta, rotación y ausentismo",
    "Auditoría de todo lo que se crea, edita o elimina",
    "Exportación a Excel de cada listado",
];

const pasos = [
    {
        titulo: "Una reunión de una hora",
        texto: "Le mostramos el sistema y dejamos configurada su empresa con usted: si trabajan sábados, cómo afectan las licencias a las vacaciones, quién administra.",
    },
    {
        titulo: "Nosotros cargamos su información",
        texto: "Usted diligencia las plantillas de Excel; nosotros las revisamos fila por fila y las cargamos. Usted no carga nada.",
    },
    {
        titulo: "Entra con su gente ya adentro",
        texto: "Le entregamos el acceso cuando sus datos están cargados. Desde la mañana siguiente empiezan a llegar las alertas.",
    },
];
</script>

<template>
    <Head title="Nexo · Gestión humana para PYMES colombianas" />

    <div class="min-h-screen bg-fondo text-texto">
        <header class="border-b border-borde bg-superficie">
            <div
                class="mx-auto flex max-w-[1280px] items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <Marca :tamano="32" />
                <Link
                    :href="conSesion ? '/' : '/acceso'"
                    class="rounded-[var(--radius-caja)] bg-accion px-4 py-2 text-tabla font-medium text-texto-invertido"
                >
                    {{ conSesion ? "Ir a mi cuenta" : "Iniciar sesión" }}
                </Link>
            </div>
        </header>

        <main>
            <section class="bg-marca text-texto-invertido">
                <div class="mx-auto max-w-[1280px] px-4 py-16 sm:px-6 sm:py-20">
                    <h1
                        class="max-w-3xl text-3xl font-semibold leading-tight sm:text-4xl"
                    >
                        Le avisamos antes de que se le venza un plazo laboral.
                    </h1>
                    <p class="mt-5 max-w-2xl text-cuerpo text-menu-texto">
                        Nexo lleva la gestión humana de su empresa —empleados,
                        contratos, vacaciones, incapacidades— y le escribe por
                        correo antes de que termine un periodo de prueba, venza
                        el preaviso de un contrato o se acumulen vacaciones. Hecho
                        para PYMES colombianas, con las reglas de la reforma
                        laboral de 2025.
                    </p>
                    <p v-if="correo" class="mt-8">
                        <a
                            :href="`mailto:${correo}?subject=Quiero una demostración de Nexo`"
                            class="inline-block rounded-[var(--radius-caja)] bg-superficie px-5 py-3 text-tabla font-semibold text-marca"
                        >
                            Pedir una demostración
                        </a>
                        <span class="ml-3 text-apoyo text-menu-apoyo">
                            o escríbanos a {{ correo }}
                        </span>
                    </p>
                </div>
            </section>

            <section class="mx-auto max-w-[1280px] px-4 py-14 sm:px-6">
                <h2 class="text-titulo font-semibold">Las alertas que recibe</h2>
                <p class="mt-2 max-w-2xl text-cuerpo text-texto-secundario">
                    Cada correo dice la fecha exacta, a quién le toca y qué dice
                    la ley. Llegan a las 6:00 de la mañana, a quien administra
                    cada módulo.
                </p>
                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="aviso in avisos"
                        :key="aviso.titulo"
                        class="rounded-[var(--radius-caja)] border border-borde bg-superficie p-5"
                    >
                        <h3 class="text-seccion font-semibold">{{ aviso.titulo }}</h3>
                        <p class="mt-2 text-tabla text-texto-secundario">{{ aviso.texto }}</p>
                    </article>
                </div>
            </section>

            <section class="border-y border-borde bg-superficie">
                <div class="mx-auto max-w-[1280px] px-4 py-14 sm:px-6">
                    <h2 class="text-titulo font-semibold">Todo lo de su gente, en un solo sitio</h2>
                    <ul class="mt-6 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        <li
                            v-for="modulo in modulos"
                            :key="modulo"
                            class="flex gap-2 text-cuerpo"
                        >
                            <span aria-hidden="true" class="text-exito">✓</span>
                            {{ modulo }}
                        </li>
                    </ul>
                </div>
            </section>

            <section class="mx-auto max-w-[1280px] px-4 py-14 sm:px-6">
                <h2 class="text-titulo font-semibold">Cómo se empieza</h2>
                <ol class="mt-8 grid gap-6 sm:grid-cols-3">
                    <li v-for="(paso, indice) in pasos" :key="paso.titulo">
                        <p class="text-apoyo font-semibold uppercase tracking-wide text-accion">
                            Paso {{ indice + 1 }}
                        </p>
                        <h3 class="mt-1 text-seccion font-semibold">{{ paso.titulo }}</h3>
                        <p class="mt-2 text-tabla text-texto-secundario">{{ paso.texto }}</p>
                    </li>
                </ol>
            </section>

            <section v-if="planes.length" class="border-y border-borde bg-superficie">
                <div class="mx-auto max-w-[1280px] px-4 py-14 sm:px-6">
                    <h2 class="text-titulo font-semibold">Precios</h2>
                    <p class="mt-2 text-cuerpo text-texto-secundario">
                        Mensuales, más IVA. El plan depende de cuántos empleados
                        gestiona y cuántas personas administran el sistema.
                    </p>
                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        <article
                            v-for="plan in planes"
                            :key="plan.nombre"
                            class="rounded-[var(--radius-caja)] border border-borde bg-fondo p-6"
                        >
                            <h3 class="text-seccion font-semibold">{{ plan.nombre }}</h3>
                            <p class="mt-3 text-cifra font-semibold text-marca">{{ plan.precio }}</p>
                            <p class="text-apoyo text-texto-secundario">al mes, más IVA</p>
                            <ul class="mt-5 space-y-2 text-tabla">
                                <li>Hasta {{ plan.empleados }} empleados</li>
                                <li>
                                    {{ plan.usuarios }}
                                    {{ plan.usuarios === 1 ? "usuario administrador" : "usuarios administradores" }}
                                </li>
                                <li v-if="plan.implementacion">
                                    Puesta en marcha: {{ plan.implementacion }}, una sola vez
                                </li>
                            </ul>
                        </article>
                    </div>
                    <p class="mt-4 text-apoyo text-texto-secundario">
                        Usuario administrador adicional: {{ usuarioAdicional }} al
                        mes, más IVA. Todos los planes incluyen todos los módulos y
                        todas las alertas.
                    </p>
                </div>
            </section>

            <section class="mx-auto max-w-[1280px] px-4 py-14 sm:px-6">
                <h2 class="text-titulo font-semibold">Sus datos, protegidos</h2>
                <div class="mt-6 grid gap-6 text-tabla text-texto-secundario sm:grid-cols-3">
                    <p>
                        <strong class="block text-texto">Cada empresa, aislada.</strong>
                        Ninguna empresa puede ver la información de otra, ni por
                        error: la base de datos misma lo impide.
                    </p>
                    <p>
                        <strong class="block text-texto">Datos de salud con cuidado.</strong>
                        El diagnóstico de una incapacidad solo lo ve quien tiene
                        permiso, y nunca queda a la vista en los registros.
                    </p>
                    <p>
                        <strong class="block text-texto">Su información es suya.</strong>
                        Si se va, se la entregamos completa y la eliminamos, como
                        exige la Ley 1581 de 2012.
                    </p>
                </div>
            </section>
        </main>

        <footer class="border-t border-borde bg-superficie">
            <div
                class="mx-auto flex max-w-[1280px] flex-col gap-4 px-4 py-8 text-apoyo text-texto-secundario sm:flex-row sm:items-start sm:justify-between sm:px-6"
            >
                <div>
                    <Marca :tamano="24" />
                    <p v-if="correo" class="mt-2">{{ correo }}</p>
                </div>
                <nav aria-label="Documentos legales">
                    <ul class="space-y-1">
                        <li v-for="documento in documentos" :key="documento.url">
                            <a :href="documento.url" class="underline">{{ documento.titulo }}</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </footer>
    </div>
</template>
