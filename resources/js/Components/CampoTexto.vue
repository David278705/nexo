<script setup>
import { computed, ref } from "vue";

defineProps({
    modelValue: { type: String, default: "" },
    etiqueta: { type: String, required: true },
    tipo: { type: String, default: "text" },
    error: { type: String, default: "" },
    // Se marcan los campos opcionales, no los obligatorios: en este sistema
    // casi todo es obligatorio (documento 3, sección 6.2).
    opcional: { type: Boolean, default: false },
    autocompletar: { type: String, default: "" },
    soloLectura: { type: Boolean, default: false },
    autoenfoque: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "salir"]);

const identificador = ref(
    `campo-${Math.random().toString(36).slice(2, 9)}`,
);

const identificadorDelError = computed(() => `${identificador.value}-error`);
</script>

<template>
    <div>
        <label
            :for="identificador"
            class="block text-sm font-medium text-texto"
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
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? identificadorDelError : undefined"
            class="mt-1 block w-full rounded-[var(--radius-caja)] border px-3 py-2 text-texto placeholder:text-texto-terciario focus:outline-none focus:ring-2 focus:ring-accion"
            :class="
                error
                    ? 'border-error bg-superficie'
                    : 'border-borde bg-superficie'
            "
            @input="emit('update:modelValue', $event.target.value)"
            @blur="emit('salir', $event.target.value)"
        />

        <p
            v-if="error"
            :id="identificadorDelError"
            class="mt-1 text-sm text-error"
        >
            {{ error }}
        </p>
    </div>
</template>
