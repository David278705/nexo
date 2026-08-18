<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    etiqueta: { type: String, required: true },
    // Cada opción es `{ valor, texto }`.
    opciones: { type: Array, default: () => [] },
    marcador: { type: String, default: "Seleccione…" },
    error: { type: String, default: "" },
    ayuda: { type: String, default: "" },
    opcional: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "salir"]);

const identificador = ref(`selector-${Math.random().toString(36).slice(2, 9)}`);
const identificadorDelError = computed(() => `${identificador.value}-error`);

// La búsqueda aparece sola cuando hay más de diez opciones (documento 3,
// sección 3.4). Es el caso de EPS, ciudades y bancos: nadie recorre 1.100
// municipios con la rueda del ratón.
const UMBRAL_DE_BUSQUEDA = 10;

const busqueda = ref("");

const tieneBusqueda = computed(
    () => props.opciones.length > UMBRAL_DE_BUSQUEDA,
);

const opcionesVisibles = computed(() => {
    const texto = busqueda.value.trim().toLocaleLowerCase("es");

    if (!tieneBusqueda.value || texto === "") {
        return props.opciones;
    }

    return props.opciones.filter((opcion) =>
        String(opcion.texto).toLocaleLowerCase("es").includes(texto),
    );
});
</script>

<template>
    <div>
        <label
            :for="identificador"
            class="block text-apoyo font-medium text-texto-secundario"
        >
            {{ etiqueta }}
            <span v-if="opcional" class="font-normal text-texto-terciario">
                (opcional)
            </span>
        </label>

        <input
            v-if="tieneBusqueda"
            v-model="busqueda"
            type="search"
            :aria-label="`Buscar en ${etiqueta}`"
            :placeholder="`Buscar en ${etiqueta.toLocaleLowerCase('es')}`"
            class="mt-1 block h-control w-full rounded-[var(--radius-caja)] border border-borde bg-superficie px-3 text-tabla text-texto placeholder:text-texto-terciario"
        />

        <select
            :id="identificador"
            :value="modelValue"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? identificadorDelError : undefined"
            class="mt-1 block h-control w-full rounded-[var(--radius-caja)] border bg-superficie px-3 text-tabla text-texto"
            :class="error ? 'border-error' : 'border-borde'"
            @change="emit('update:modelValue', $event.target.value)"
            @blur="emit('salir', $event.target.value)"
        >
            <option value="">{{ marcador }}</option>
            <option
                v-for="opcion in opcionesVisibles"
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
