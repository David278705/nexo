<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import Armazon from "@/Components/Armazon.vue";
import Aviso from "@/Components/Aviso.vue";
import DistribucionLista from "@/Components/DistribucionLista.vue";
import TarjetaDeAccion from "@/Components/TarjetaDeAccion.vue";
import TarjetaIndicador from "@/Components/TarjetaIndicador.vue";

/*
 * El panel de inicio (documento 3, sección 5.2). La pantalla más importante del
 * sistema: responde «¿qué tengo que hacer hoy?» antes de mostrar ninguna
 * estadística, y por eso el bloque de acción va primero y solo.
 *
 * Los seis bloques de indicadores del capítulo 12 van debajo (F4-04), en el
 * orden en que los manda el servidor: primero lo que exige acción, después el
 * estado de la empresa.
 *
 * Aquí no se cuenta ni se decide nada. La fecha llega ya escrita del servidor,
 * en la zona horaria de la aplicación —formatearla en el navegador la ataría al
 * reloj de cada máquina—, y las tarjetas llegan con su cifra, su texto y su
 * destino.
 */
const props = defineProps({
    fecha: { type: String, required: true },
    acciones: { type: Array, default: () => [] },
    /*
     * Las que llegan en la segunda vuelta de Inertia. Mientras no están, el
     * bloque se ve completo con las demás: una tarjeta que aparece un momento
     * después es mucho mejor que un panel que no abre hasta tenerla.
     */
    accionesDiferidas: { type: Array, default: null },
    /*
     * Los seis bloques del capítulo 12, cada uno con la misma forma —título,
     * tarjetas y distribuciones, y a veces avisos—, así que un solo bucle basta
     * para pintarlos todos. También llega diferido: ninguno de los seis lleva a
     * ningún sitio, así que no hay ninguna prisa por tenerlo en la primera vuelta.
     */
    indicadores: { type: Array, default: null },
});

const tarjetas = computed(() => [
    ...props.acciones,
    ...(props.accionesDiferidas ?? []),
]);

const nombre = computed(() => usePage().props.usuario?.nombre ?? "");

// Solo el nombre de pila. «Buenos días, María Fernanda Gutiérrez Ríos» no
// saluda a nadie.
const nombreDePila = computed(() => nombre.value.trim().split(/\s+/)[0] ?? "");
</script>

<template>
    <Head title="Inicio" />

    <Armazon>
        <h1 class="text-titulo font-semibold text-texto">
            Hola, {{ nombreDePila }}
        </h1>
        <p class="mt-1 text-apoyo text-texto-secundario">{{ fecha }}</p>

        <section class="mt-6">
            <h2 class="text-seccion font-semibold text-texto">
                Requiere tu atención
            </h2>

            <div
                v-if="tarjetas.length > 0"
                class="mt-3 grid gap-3 lg:grid-cols-2 xl:grid-cols-4"
            >
                <TarjetaDeAccion
                    v-for="accion in tarjetas"
                    :key="accion.enlace"
                    :valor="accion.valor"
                    :etiqueta="accion.etiqueta"
                    :enlace="accion.enlace"
                    :accion="accion.accion"
                    :tono="accion.tono"
                    :extras="accion.extras"
                />
            </div>

            <!--
                Una tarjeta en cero no se pinta, así que cuando no queda ninguna
                hay que decirlo con una frase: un bloque vacío se lee como una
                pantalla a medio cargar.

                Se espera a que hayan llegado también las diferidas: decir «nada
                exige tu atención» y desdecirse un segundo después es peor que
                no decir nada.
            -->
            <p
                v-else-if="accionesDiferidas !== null"
                class="mt-3 text-apoyo text-texto-secundario"
            >
                Nada exige tu atención hoy: no hay contratos por vencer,
                periodos de prueba que terminen esta semana ni vacaciones
                acumuladas de más.
            </p>
        </section>

        <section
            v-for="bloque in indicadores ?? []"
            :key="bloque.titulo"
            class="mt-6"
        >
            <h2 class="text-seccion font-semibold text-texto">
                {{ bloque.titulo }}
            </h2>

            <div
                v-if="bloque.tarjetas.length > 0"
                class="mt-3 grid gap-3 lg:grid-cols-2 xl:grid-cols-4"
            >
                <TarjetaIndicador
                    v-for="tarjeta in bloque.tarjetas"
                    :key="tarjeta.etiqueta"
                    :valor="tarjeta.valor"
                    :etiqueta="tarjeta.etiqueta"
                    :variacion="tarjeta.variacion ?? ''"
                    :tendencia="tarjeta.tendencia ?? ''"
                    :nota="tarjeta.nota ?? ''"
                />
            </div>

            <div
                v-if="bloque.distribuciones.length > 0"
                class="mt-3 grid gap-3 lg:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="distribucion in bloque.distribuciones"
                    :key="distribucion.titulo"
                    class="rounded-[var(--radius-modal)] border border-borde bg-superficie p-4"
                >
                    <h3 class="text-cuerpo font-semibold text-texto">
                        {{ distribucion.titulo }}
                    </h3>
                    <DistribucionLista class="mt-3" :items="distribucion.items" />
                </div>
            </div>

            <Aviso
                v-for="(aviso, indice) in bloque.avisos ?? []"
                :key="indice"
                class="mt-3"
            >
                {{ aviso }}
            </Aviso>
        </section>
    </Armazon>
</template>
