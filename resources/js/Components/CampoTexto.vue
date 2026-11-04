<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    modelValue: { type: String, default: "" },
    etiqueta: { type: String, required: true },
    tipo: { type: String, default: "text" },
    error: { type: String, default: "" },
    // El mensaje de error reemplaza al texto de ayuda, no se apilan
    // (documento 3, sección 3.4).
    ayuda: { type: String, default: "" },
    // Se marcan los campos opcionales, no los obligatorios: en este sistema
    // casi todo es obligatorio (documento 3, sección 6.2).
    opcional: { type: Boolean, default: false },
    autocompletar: { type: String, default: "" },
    soloLectura: { type: Boolean, default: false },
    autoenfoque: { type: Boolean, default: false },
    // Límites nativos de un campo de tipo `date`. Vacíos en cualquier otro
    // campo, así que no afectan a nadie más.
    minimo: { type: String, default: "" },
    maximo: { type: String, default: "" },
    // El teclado que ofrece el celular (`inputmode`): «decimal» para el
    // dinero, que se escribe en un campo de texto y no en uno `number`.
    modoTeclado: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "salir"]);

const identificador = ref(`campo-${Math.random().toString(36).slice(2, 9)}`);

const identificadorDelError = computed(() => `${identificador.value}-error`);
const identificadorDeLaAyuda = computed(() => `${identificador.value}-ayuda`);

const descripcion = computed(() => {
    if (props.error) {
        return identificadorDelError.value;
    }

    return props.ayuda ? identificadorDeLaAyuda.value : undefined;
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
            :id="identificador"
            :type="tipo"
            :value="modelValue"
            :autocomplete="autocompletar || undefined"
            :readonly="soloLectura"
            :autofocus="autoenfoque"
            :min="minimo || undefined"
            :max="maximo || undefined"
            :inputmode="modoTeclado || undefined"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="descripcion"
            class="mt-1 block h-control w-full rounded-[var(--radius-caja)] border bg-superficie px-3 text-tabla text-texto placeholder:text-texto-terciario"
            :class="error ? 'border-error' : 'border-borde'"
            @input="emit('update:modelValue', $event.target.value)"
            @blur="emit('salir', $event.target.value)"
        />

        <p
            v-if="error"
            :id="identificadorDelError"
            class="mt-1 text-apoyo text-error"
        >
            {{ error }}
        </p>
        <p
            v-else-if="ayuda"
            :id="identificadorDeLaAyuda"
            class="mt-1 text-apoyo text-texto-terciario"
        >
            {{ ayuda }}
        </p>
    </div>
</template>
