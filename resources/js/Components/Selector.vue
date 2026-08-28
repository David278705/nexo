<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    etiqueta: { type: String, required: true },
    // Cada opción es `{ valor, texto }`.
    opciones: { type: Array, default: () => [] },
    marcador: { type: String, default: "Seleccione…" },
    /*
     * Hay selectores que nunca están sin elegir: los filtros de lista cerrada
     * traen siempre un valor, y su opción «todos» ya está entre las opciones.
     * Ahí la línea de marcador sobra y confunde, porque parece una opción más
     * que hace otra cosa.
     */
    conMarcador: { type: Boolean, default: true },
    error: { type: String, default: "" },
    ayuda: { type: String, default: "" },
    opcional: { type: Boolean, default: false },
    /*
     * Un selector cuya opción decide el sistema y no quien digita. Es el motivo
     * de la baja en la expiración del plazo y en la obra terminada, donde el
     * documento 1, 7.3 dice que hay un solo motivo y el sistema lo autocompleta:
     * ofrecer una lista de una sola opción invita a buscar las otras.
     */
    deshabilitado: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "salir"]);

const identificador = ref(`selector-${Math.random().toString(36).slice(2, 9)}`);
const identificadorDelError = computed(() => `${identificador.value}-error`);
const identificadorDeLaLista = computed(() => `${identificador.value}-lista`);

// La búsqueda aparece sola cuando hay más de diez opciones (documento 3,
// sección 3.4). Es el caso de EPS, ciudades y bancos: nadie recorre 1.100
// municipios con la rueda del ratón.
const UMBRAL_DE_BUSQUEDA = 10;

const tieneBusqueda = computed(
    () => props.opciones.length > UMBRAL_DE_BUSQUEDA,
);

/*
 * Todas las opciones que se pueden elegir, marcador incluido cuando aplica:
 * es la misma lista que antes vivía repartida entre `<option>` sueltos, ahora
 * en un solo sitio porque el marcador también se busca y se resalta como
 * cualquier otra.
 */
const todasLasOpciones = computed(() => {
    const lista = props.conMarcador
        ? [{ valor: "", texto: props.marcador }, ...props.opciones]
        : props.opciones;

    return lista;
});

const opcionElegida = computed(
    () => props.opciones.find((opcion) => opcion.valor === props.modelValue) ?? null,
);

/*
 * El combobox de una sola caja (documento 3, sección 3.4 y regla de
 * interfaz): antes eran dos controles —una caja de búsqueda y, debajo, un
 * `<select>` nativo aparte que había que volver a abrir—. Escribir filtraba
 * una lista que no se veía hasta el segundo clic. Aquí hay un único campo:
 * se escribe y la lista de resultados aparece debajo de una vez, se navega
 * con las flechas y se elige con Enter o con el clic, igual que cualquier
 * buscador con autocompletar.
 */
const abierto = ref(false);
const texto = ref("");
const resaltado = ref(-1);
const contenedor = ref(null);
const campoDeTexto = ref(null);

function etiquetaDe(opcion) {
    return opcion?.texto ?? "";
}

// El texto de la caja cuando no se está escribiendo: el de la opción elegida,
// o el marcador si no hay ninguna —igual que un `<select>` sin nada marcado—.
function textoEnReposo() {
    return opcionElegida.value ? etiquetaDe(opcionElegida.value) : "";
}

watch(
    () => props.modelValue,
    () => {
        if (!abierto.value) {
            texto.value = textoEnReposo();
        }
    },
    { immediate: true },
);

const opcionesVisibles = computed(() => {
    const consulta = texto.value.trim().toLocaleLowerCase("es");

    // Con la opción ya elegida escrita en la caja, no se filtra: si se
    // filtrara por su propio texto, cualquier otra opción con una palabra
    // distinta desaparecería de la lista antes de que la persona haya escrito
    // nada de su propia mano.
    if (!abierto.value || consulta === "" || consulta === textoEnReposo().trim().toLocaleLowerCase("es")) {
        return todasLasOpciones.value;
    }

    return todasLasOpciones.value.filter((opcion) =>
        String(opcion.texto).toLocaleLowerCase("es").includes(consulta),
    );
});

function abrir() {
    if (props.deshabilitado || abierto.value) {
        return;
    }

    abierto.value = true;
    resaltado.value = -1;
}

function cerrar({ revertir = true } = {}) {
    abierto.value = false;
    resaltado.value = -1;

    if (revertir) {
        texto.value = textoEnReposo();
    }
}

function elegir(opcion) {
    texto.value = etiquetaDe(opcion);
    cerrar({ revertir: false });
    emit("update:modelValue", opcion.valor);
    emit("salir", opcion.valor);
}

function alEscribir(evento) {
    texto.value = evento.target.value;
    abrir();
    resaltado.value = opcionesVisibles.value.length > 0 ? 0 : -1;
}

function moverResaltado(direccion) {
    if (!abierto.value) {
        abrir();
        return;
    }

    const total = opcionesVisibles.value.length;

    if (total === 0) {
        return;
    }

    resaltado.value = (resaltado.value + direccion + total) % total;

    // `scrollIntoView` no existe en jsdom (las pruebas de Vitest corren
    // ahí), así que se comprueba antes de llamarlo en vez de asumir que todo
    // entorno lo tiene.
    nextTick(() => {
        document
            .getElementById(`${identificador.value}-opcion-${resaltado.value}`)
            ?.scrollIntoView?.({ block: "nearest" });
    });
}

function alPulsarTecla(evento) {
    if (evento.key === "ArrowDown") {
        evento.preventDefault();
        moverResaltado(1);
    } else if (evento.key === "ArrowUp") {
        evento.preventDefault();
        moverResaltado(-1);
    } else if (evento.key === "Enter") {
        evento.preventDefault();

        if (abierto.value && resaltado.value >= 0) {
            elegir(opcionesVisibles.value[resaltado.value]);
        }
    } else if (evento.key === "Escape") {
        if (abierto.value) {
            evento.preventDefault();
            cerrar();
        }
    } else if (evento.key === "Tab") {
        cerrar({ revertir: opcionesVisibles.value.length !== 1 || !abierto.value });
    }
}

function alSalir() {
    // Un `blur` que va hacia una opción de la propia lista no cuenta como
    // salir del campo: `@mousedown.prevent` en cada opción evita que el
    // input pierda el foco antes del clic, así que esto solo dispara cuando
    // de verdad se abandona el combobox.
    cerrar();
    emit("salir", props.modelValue);
}

function alHacerClicAfuera(evento) {
    if (abierto.value && contenedor.value && !contenedor.value.contains(evento.target)) {
        cerrar();
    }
}

onMounted(() => document.addEventListener("click", alHacerClicAfuera));
onBeforeUnmount(() => document.removeEventListener("click", alHacerClicAfuera));
</script>

<template>
    <div ref="contenedor" class="relative">
        <label
            :for="identificador"
            class="block text-apoyo font-medium text-texto-secundario"
        >
            {{ etiqueta }}
            <span v-if="opcional" class="font-normal text-texto-terciario">
                (opcional)
            </span>
        </label>

        <template v-if="tieneBusqueda">
            <input
                :id="identificador"
                ref="campoDeTexto"
                :value="texto"
                type="text"
                role="combobox"
                autocomplete="off"
                :aria-expanded="abierto"
                :aria-controls="identificadorDeLaLista"
                :aria-activedescendant="resaltado >= 0 ? `${identificador}-opcion-${resaltado}` : undefined"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? identificadorDelError : undefined"
                :disabled="deshabilitado"
                :placeholder="conMarcador ? marcador : `Buscar en ${etiqueta.toLocaleLowerCase('es')}`"
                class="mt-1 block h-control w-full rounded-[var(--radius-caja)] border bg-superficie px-3 text-tabla text-texto placeholder:text-texto-terciario"
                :class="[error ? 'border-error' : 'border-borde', deshabilitado ? 'bg-neutro-fondo' : '']"
                @input="alEscribir"
                @focus="abrir"
                @keydown="alPulsarTecla"
                @blur="alSalir"
            />

            <ul
                v-if="abierto"
                :id="identificadorDeLaLista"
                role="listbox"
                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-[var(--radius-caja)] border border-borde bg-superficie py-1 shadow-flotante"
            >
                <li
                    v-if="opcionesVisibles.length === 0"
                    class="px-3 py-2 text-tabla text-texto-terciario"
                >
                    Sin resultados
                </li>
                <li
                    v-for="(opcion, indice) in opcionesVisibles"
                    :id="`${identificador}-opcion-${indice}`"
                    :key="opcion.valor"
                    role="option"
                    :aria-selected="opcion.valor === modelValue"
                    class="cursor-pointer px-3 py-2 text-tabla"
                    :class="
                        indice === resaltado
                            ? 'bg-accion text-texto-invertido'
                            : 'text-texto hover:bg-fondo'
                    "
                    @mousedown.prevent="elegir(opcion)"
                    @mouseenter="resaltado = indice"
                >
                    {{ opcion.texto }}
                </li>
            </ul>
        </template>

        <select
            v-else
            :id="identificador"
            :value="modelValue"
            :disabled="deshabilitado"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? identificadorDelError : undefined"
            class="mt-1 block h-control w-full rounded-[var(--radius-caja)] border px-3 text-tabla text-texto"
            :class="[
                error ? 'border-error' : 'border-borde',
                deshabilitado ? 'bg-neutro-fondo' : 'bg-superficie',
            ]"
            @change="emit('update:modelValue', $event.target.value)"
            @blur="emit('salir', $event.target.value)"
        >
            <option v-if="conMarcador" value="">{{ marcador }}</option>
            <option
                v-for="opcion in opciones"
                :key="opcion.valor"
                :value="opcion.valor"
            >
                {{ opcion.texto }}
            </option>
        </select>

        <p
            v-if="error"
            :id="identificadorDelError"
            class="mt-1 text-apoyo text-error"
        >
            {{ error }}
        </p>
        <p v-else-if="ayuda" class="mt-1 text-apoyo text-texto-terciario">
            {{ ayuda }}
        </p>
    </div>
</template>
