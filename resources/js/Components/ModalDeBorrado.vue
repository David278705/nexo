<script setup>
import Boton from "@/Components/Boton.vue";
import Modal from "@/Components/Modal.vue";

/*
 * La confirmación de una eliminación (documento 3, 2.4 y 6.3).
 *
 * Es un solo componente para los tres módulos de novedades por lo mismo que
 * `ModalDeNovedad`: tres copias de una confirmación se separan a la primera
 * corrección, y esta es la pantalla donde el usuario decide si pierde un
 * registro.
 *
 * **Aquí no se escribe ni se calcula el mensaje.** Llega entero del servidor,
 * de `App\Novedades\ConfirmacionDeBorrado`, con la consecuencia ya contada por
 * el motor: «Vas a eliminar las vacaciones del 1 al 8 de julio de Carlos
 * Ramírez. Se eliminarán 8 días de vacaciones y el saldo de Carlos Ramírez
 * pasará de 4,25 a 12,25 días. Esta acción queda registrada en la auditoría.»
 * El componente pone el modal, el botón rojo y nada más.
 *
 * El botón destructivo vive aquí y solo aquí: la regla del documento 3, 3.4 es
 * que el rojo nunca anda suelto en una tabla.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    titulo: { type: String, default: "Eliminar registro" },
    // La frase completa que escribió el servidor. Vacía mientras la recarga
    // parcial que la trae no ha contestado todavía.
    mensaje: { type: String, default: "" },
    enviando: { type: Boolean, default: false },
});

const emit = defineEmits(["cerrar", "confirmar"]);

/*
 * Sin consecuencia escrita no se elimina. El atributo `disabled` del botón ya
 * lo impide en pantalla, pero la comprobación va también aquí: eliminar algo
 * sin haber leído qué se pierde es exactamente lo que este modal existe para
 * evitar.
 */
function confirmar() {
    if (props.mensaje === "" || props.enviando) {
        return;
    }

    emit("confirmar");
}
</script>

<template>
    <Modal :abierto="abierto" :titulo="titulo" @cerrar="emit('cerrar')">
        <p class="text-cuerpo text-texto">
            {{ mensaje || "Calculando la consecuencia…" }}
        </p>

        <template #pie>
            <Boton variante="texto" tipo="button" @click="emit('cerrar')">
                Cancelar
            </Boton>
            <Boton
                variante="destructivo"
                tipo="button"
                :enviando="enviando"
                :deshabilitado="mensaje === ''"
                @click="confirmar"
            >
                Eliminar
            </Boton>
        </template>
    </Modal>
</template>
