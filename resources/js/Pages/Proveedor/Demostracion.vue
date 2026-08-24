<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import TablaDatos from "@/Components/TablaDatos.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * Demostración (documento 4, capítulo 10): el estado del ambiente ficticio,
 * su botón de restaurar y los accesos de prueba con vigencia limitada del
 * proceso comercial (12.1).
 *
 * `diasDeVigencia` no trae ningún valor por omisión a propósito: el documento
 * 6, D.12, deja sin decidir cuánto debe durar un acceso de demostración, y el
 * documento 4 dice que es David quien lo decide cada vez que lo genera. Un
 * número ya puesto en el campo sería esa misma decisión, escondida en el
 * marcador del formulario en vez de en un archivo de configuración.
 */
const props = defineProps({
    restauradoEn: { type: String, default: null },
    personas: { type: Number, required: true },
    accesosActivos: { type: Array, required: true },
    enlaceGenerado: { type: String, default: null },
});

const RUTA = "/proveedor/demostracion";

const restaurando = ref(false);

function restaurar() {
    restaurando.value = true;
    router.post(
        `${RUTA}/restaurar`,
        {},
        { preserveScroll: true, onFinish: () => (restaurando.value = false) },
    );
}

const formulario = useForm({
    nombre_prospecto: "",
    dias_de_vigencia: "",
});

function generar() {
    formulario.post(`${RUTA}/accesos`, {
        preserveScroll: true,
        onSuccess: () => formulario.reset(),
    });
}

function revocar(acceso) {
    router.post(
        `${RUTA}/accesos/${acceso.id}/revocar`,
        {},
        { preserveScroll: true },
    );
}

const copiado = ref(false);

async function copiar() {
    try {
        await navigator.clipboard.writeText(props.enlaceGenerado);
        copiado.value = true;
    } catch {
        copiado.value = false;
    }
}

const columnas = [
    { clave: "nombreProspecto", titulo: "Prospecto" },
    { clave: "generadoEn", titulo: "Generado" },
    { clave: "expiraEn", titulo: "Vence" },
    { clave: "diasDeVigencia", titulo: "Días", numerica: true },
    { clave: "ultimoUso", titulo: "Último uso" },
    { clave: "acciones", titulo: "" },
];
</script>

<template>
    <Head title="Demostración" />

    <ArmazonProveedor>
        <div>
            <h1 class="text-titulo font-semibold text-texto">Demostración</h1>
            <p class="mt-1 text-apoyo text-texto-secundario">
                El ambiente ficticio que se enseña en la sesión guiada y los
                accesos de prueba que exploran solos
            </p>
        </div>

        <div
            class="mt-5 flex flex-wrap items-center gap-4 rounded-[var(--radius-modal)] border border-borde bg-superficie p-4"
        >
            <TarjetaIndicador
                :valor="personas"
                etiqueta="Personas en la planta de demostración"
            />

            <div class="flex-1">
                <p class="text-apoyo text-texto-secundario">
                    {{
                        restauradoEn
                            ? `Restaurada por última vez el ${restauradoEn}.`
                            : "Todavía no se ha restaurado en esta instalación."
                    }}
                </p>
                <p class="mt-1 text-apoyo text-texto-terciario">
                    El proceso nocturno la restaura solo, todos los días a las
                    3:00 a. m. Este botón hace lo mismo, ahora mismo.
                </p>
            </div>

            <Boton
                tipo="button"
                variante="secundario"
                :enviando="restaurando"
                @click="restaurar"
            >
                Restaurar ahora
            </Boton>
        </div>

        <div
            v-if="enlaceGenerado"
            class="mt-4 rounded-[var(--radius-caja)] border border-exito-borde bg-exito-fondo p-4"
        >
            <p class="font-semibold text-exito-texto">
                Acceso generado. Cópialo y envíaselo al prospecto: no se vuelve
                a mostrar en esta pantalla.
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <code
                    class="rounded-[var(--radius-caja)] border border-borde bg-superficie px-3 py-1.5 text-tabla text-texto"
                    >{{ enlaceGenerado }}</code
                >
                <Boton tipo="button" variante="secundario" @click="copiar">
                    Copiar enlace
                </Boton>
                <span v-if="copiado" role="status" class="text-apoyo text-exito">
                    Copiado al portapapeles.
                </span>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[360px_1fr]">
            <div
                class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-4"
            >
                <h2 class="text-seccion font-semibold text-texto">
                    Generar un acceso de prueba
                </h2>
                <p class="mt-1 text-apoyo text-texto-secundario">
                    Para la etapa «Prueba» del proceso comercial: el prospecto
                    lo explora solo, con vigencia limitada.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="generar">
                    <CampoTexto
                        v-model="formulario.nombre_prospecto"
                        etiqueta="A quién se le entrega"
                        ayuda="Es solo para identificarlo en la lista de abajo."
                        :error="formulario.errors.nombre_prospecto"
                    />

                    <CampoTexto
                        v-model="formulario.dias_de_vigencia"
                        tipo="number"
                        etiqueta="Días de vigencia"
                        ayuda="Sin sugerencia: decide cuánto debe durar cada vez."
                        :error="formulario.errors.dias_de_vigencia"
                    />

                    <Boton
                        tipo="submit"
                        ancho
                        :enviando="formulario.processing"
                    >
                        Generar acceso
                    </Boton>
                </form>
            </div>

            <div>
                <h2 class="text-seccion font-semibold text-texto">
                    Accesos activos
                </h2>

                <div class="mt-3">
                    <TablaDatos
                        :columnas="columnas"
                        :filas="accesosActivos"
                        :clicable="false"
                        titulo-vacio="No hay ningún acceso de prueba activo"
                        descripcion-vacio="Los que generes para la etapa «Prueba» del proceso comercial aparecerán aquí mientras estén vigentes."
                    >
                        <template #celda-ultimoUso="{ fila }">
                            {{ fila.ultimoUso ?? "Todavía no se ha usado" }}
                        </template>
                        <template #celda-acciones="{ fila }">
                            <Boton
                                tipo="button"
                                variante="texto"
                                @click="revocar(fila)"
                            >
                                Revocar
                            </Boton>
                        </template>
                    </TablaDatos>
                </div>
            </div>
        </div>

        <Aviso tono="informacion" class="mt-6">
            El botón de arriba también corre cada noche por su cuenta: es lo
            que garantiza que la demostración siempre luzca igual de poblada,
            así un prospecto la haya dejado desordenada el día anterior.
        </Aviso>
    </ArmazonProveedor>
</template>
