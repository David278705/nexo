import { router } from "@inertiajs/vue3";
import { ref } from "vue";

/*
 * Una acción que dispara una petición al servidor desde una fila de una tabla
 * y necesita su propio indicador de carga, distinto del de las demás filas
 * (documento 3, 2.5, aplicado fila por fila en F6-04).
 *
 * El mismo bloque —un `ref` con el identificador de la fila en curso, fijado
 * antes de la petición y limpiado en `onFinish`— se había vuelto a escribir a
 * mano seis veces en cuatro pantallas del panel del proveedor: activar o
 * desactivar una entidad, un municipio o un plan; reenviar una invitación o el
 * enlace de contraseña; reintentar un trabajo de la cola.
 */

/**
 * @returns {{ activo: import("vue").Ref<*>, ejecutar: (id: string|number, ruta: string, datos?: object) => void }}
 */
export function usarAccionPorFila() {
    const activo = ref(null);

    function ejecutar(id, ruta, datos = {}) {
        activo.value = id;

        router.post(ruta, datos, {
            preserveScroll: true,
            onFinish: () => {
                activo.value = null;
            },
        });
    }

    return { activo, ejecutar };
}
