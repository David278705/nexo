<script setup>
import Boton from "@/Components/Boton.vue";
import CampoFecha from "@/Components/CampoFecha.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Modal from "@/Components/Modal.vue";

/*
 * El formulario de alta o corrección de un registro de catálogo.
 *
 * Es el mismo modal para las cinco listas de entidades, para los municipios y
 * para los festivos: lo único que cambia entre ellos son los campos, que llegan
 * declarados desde la pantalla. Tres modales con la misma forma se habrían
 * separado a la primera corrección.
 *
 * El formulario lo trae la pantalla —es un `useForm` de Inertia— porque es ella
 * la que sabe a qué dirección va cada acción. Aquí solo se pinta y se pide
 * guardar; los errores son los que devolvió el servidor.
 */
defineProps({
    abierto: { type: Boolean, default: false },
    titulo: { type: String, required: true },
    // Cada campo es `{ clave, etiqueta, tipo, ayuda, opcional }`.
    campos: { type: Array, required: true },
    formulario: { type: Object, required: true },
    textoBoton: { type: String, default: "Guardar" },
});

const emit = defineEmits(["cerrar", "guardar"]);
</script>

<template>
    <Modal :abierto="abierto" :titulo="titulo" @cerrar="emit('cerrar')">
        <div class="grid gap-4">
            <component
                :is="campo.tipo === 'fecha' ? CampoFecha : CampoTexto"
                v-for="campo in campos"
                :key="campo.clave"
                v-model="formulario[campo.clave]"
                :etiqueta="campo.etiqueta"
                :ayuda="campo.ayuda ?? ''"
                :opcional="campo.opcional ?? false"
                :error="formulario.errors[campo.clave]"
            />
        </div>

        <template #pie>
            <Boton variante="texto" tipo="button" @click="emit('cerrar')">
                Cancelar
            </Boton>
            <Boton tipo="button" :enviando="formulario.processing" @click="emit('guardar')">
                {{ textoBoton }}
            </Boton>
        </template>
    </Modal>
</template>
