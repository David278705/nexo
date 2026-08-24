<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ArmazonProveedor from "@/Components/Proveedor/ArmazonProveedor.vue";
import Aviso from "@/Components/Aviso.vue";
import Boton from "@/Components/Boton.vue";
import EstadoVacio from "@/Components/EstadoVacio.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * Salud del sistema (documento 4, capítulo 9): «lo que hay que mirar cada
 * mañana en menos de un minuto». Todo llega calculado de
 * `App\Proveedor\SaludDelSistema`.
 *
 * Las copias de seguridad son la única pieza que no se enseña como las demás:
 * `copias.disponible` es `false` porque la infraestructura de la que dependen
 * sigue sin decidirse (documento 6, A.5), y el texto lo explica en vez de
 * fingir una fecha.
 */
const props = defineProps({
    proceso: { type: Object, required: true },
    cola: { type: Object, required: true },
    errores: { type: Object, required: true },
    copias: { type: Object, required: true },
    espacioBd: { type: String, required: true },
    correosDelMes: { type: Number, required: true },
});

const procesoDeHoy = computed(() => {
    if (props.proceso.hoy === null) {
        return { valor: "Sin ejecutar", nota: "Todavía no ha corrido hoy", notaTono: "error" };
    }

    const { hora, duracion, resultado, exitosa } = props.proceso.hoy;

    return {
        valor: hora,
        nota: (exitosa ? "Corrió bien · " : "Con fallos · ") + duracion + " · " + resultado,
        notaTono: exitosa ? "" : "error",
    };
});

const reintentando = ref(null);

function reintentar(uuid) {
    reintentando.value = uuid;

    router.post(
        `/proveedor/salud/cola/${uuid}/reintentar`,
        {},
        { preserveScroll: true, onFinish: () => (reintentando.value = null) },
    );
}
</script>

<template>
    <Head title="Salud del sistema" />

    <ArmazonProveedor>
        <h1 class="text-titulo font-semibold text-texto">Salud del sistema</h1>
        <p class="mt-1 text-apoyo text-texto-secundario">
            Lo que hay que mirar cada mañana en menos de un minuto
        </p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TarjetaIndicador
                :valor="procesoDeHoy.valor"
                etiqueta="Proceso de alertas de hoy"
                :nota="procesoDeHoy.nota"
                :nota-tono="procesoDeHoy.notaTono"
            />
            <TarjetaIndicador
                :valor="cola.fallidos.length"
                etiqueta="Trabajos en cola fallidos"
                :nota="cola.pendientes + ' pendientes'"
                :nota-tono="cola.fallidos.length > 0 ? 'error' : ''"
            />
            <TarjetaIndicador
                :valor="errores.ultimas24h"
                etiqueta="Errores de aplicación en 24 h"
                :nota="errores.ultimas24h > 0 ? 'Ver el detalle abajo' : ''"
                :nota-tono="errores.ultimas24h > 0 ? 'advertencia' : ''"
            />
            <TarjetaIndicador
                :valor="copias.disponible ? 'Al día' : 'Sin automatizar'"
                etiqueta="Copias de seguridad"
                nota="Depende de una decisión pendiente"
                nota-tono="advertencia"
            />
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-4">
                <h3 class="text-cuerpo font-semibold text-texto">Costos que crecen</h3>
                <dl class="mt-3 space-y-2 text-apoyo">
                    <div class="flex justify-between">
                        <dt class="text-texto-secundario">Tamaño de la base de datos</dt>
                        <dd class="tabular-nums text-texto">{{ espacioBd }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-texto-secundario">Correos enviados este mes</dt>
                        <dd class="tabular-nums text-texto">{{ correosDelMes }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-4">
                <h3 class="text-cuerpo font-semibold text-texto">
                    Últimas ejecuciones del proceso diario
                </h3>
                <EstadoVacio
                    v-if="proceso.historial.length === 0"
                    titulo="Todavía no ha corrido ninguna"
                    class="mt-2"
                />
                <table v-else class="mt-3 w-full text-apoyo">
                    <tbody>
                        <tr
                            v-for="(fila, indice) in proceso.historial"
                            :key="indice"
                            class="border-t border-borde"
                        >
                            <td class="py-2 text-texto-secundario">{{ fila.fecha }} {{ fila.hora }}</td>
                            <td class="py-2 tabular-nums text-texto">{{ fila.duracion }}</td>
                            <td class="py-2 tabular-nums text-texto">{{ fila.resultado }}</td>
                            <td class="py-2 text-right">
                                <span :class="fila.exitosa ? 'text-exito-texto' : 'text-error-texto'">
                                    {{ fila.exitosa ? "OK" : "Con fallos" }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="cola.fallidos.length > 0" class="mt-5">
            <h3 class="text-cuerpo font-semibold text-texto">Trabajos en cola fallidos</h3>
            <table class="mt-3 w-full text-apoyo">
                <tbody>
                    <tr
                        v-for="trabajo in cola.fallidos"
                        :key="trabajo.uuid"
                        class="border-t border-borde"
                    >
                        <td class="py-2 text-texto-secundario">{{ trabajo.cola }}</td>
                        <td class="py-2 text-texto">{{ trabajo.excepcion }}</td>
                        <td class="py-2 text-texto-secundario">{{ trabajo.falloEn }}</td>
                        <td class="py-2 text-right">
                            <Boton
                                tipo="button"
                                variante="secundario"
                                :enviando="reintentando === trabajo.uuid"
                                @click="reintentar(trabajo.uuid)"
                            >
                                Reintentar
                            </Boton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="errores.filas.length > 0" class="mt-5">
            <h3 class="text-cuerpo font-semibold text-texto">Errores recientes</h3>
            <table class="mt-3 w-full text-apoyo">
                <tbody>
                    <tr
                        v-for="(error, indice) in errores.filas"
                        :key="indice"
                        class="border-t border-borde"
                    >
                        <td class="py-2 text-texto-secundario">{{ error.ocurrido }}</td>
                        <td class="py-2 font-medium text-texto">{{ error.clase }}</td>
                        <td class="py-2 text-texto-secundario">{{ error.mensaje }}</td>
                        <td class="py-2 text-right text-texto-secundario">{{ error.contexto }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Aviso tono="advertencia" class="mt-5">
            {{ copias.texto }}
        </Aviso>
    </ArmazonProveedor>
</template>
