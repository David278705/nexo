import { router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

/*
 * Editar y eliminar una novedad, del lado de la interfaz (documento 1, 6.2).
 *
 * Es el gemelo de `App\Novedades\DetalleDeNovedad`, y el hermano de
 * `useListado`: las tres pantallas de novedades hacían exactamente lo mismo con
 * datos distintos, así que lo hacen una sola vez aquí.
 *
 * El flujo de las dos acciones es el mismo y por eso viven juntas:
 *
 * 1. El usuario pulsa Editar o Eliminar en una fila.
 * 2. La pantalla pide al servidor el detalle de esa novedad con una recarga
 *    parcial —`router.reload({ only: ["novedad"] })`—, porque el listado no
 *    trae ni la ficha del empleado ni la consecuencia calculada del borrado, y
 *    cargarlas para las veinticinco filas sería pagar por lo que casi nunca se
 *    usa.
 * 3. Cuando el detalle llega, se abre el modal que corresponda: el de edición
 *    con el formulario ya relleno, o el de borrado con la frase del servidor.
 *
 * **Aquí no se calcula nada** —ni días, ni saldos, ni la frase de la
 * confirmación—: todo eso llega escrito, que es la regla 2 del proyecto.
 */

/**
 * @param {object} declaracion
 * @param {string} declaracion.ruta La ruta del módulo, sin barra final. De ella
 *   salen la de editar y la de eliminar: `ruta/7`.
 * @param {object} declaracion.form El `useForm` de la pantalla, el mismo que
 *   usa el registro. Al abrir la edición se rellena con los valores que manda
 *   el servidor, así que los cálculos que ya observaban el formulario —los días
 *   y el saldo— se vuelven a pedir solos.
 * @param {() => object} declaracion.novedad La prop `novedad` tal como llega
 *   del servidor. Se lee como función para que siga siendo reactiva.
 */
export function useNovedad({ ruta, form, novedad }) {
    // Qué se está haciendo —"editar", "eliminar" o nada— y sobre qué fila.
    const accion = ref(null);
    const seleccionada = ref(null);
    const enviando = ref(false);

    /*
     * El detalle solo cuenta si es el de la fila que se acaba de pulsar. Sin
     * esa comprobación, pulsar Eliminar en una fila enseñaría por un instante
     * la consecuencia calculada de la anterior, que es exactamente el número
     * que el usuario está a punto de aceptar.
     */
    const detalle = computed(() => {
        const respuesta = novedad();

        return respuesta && respuesta.id === seleccionada.value
            ? respuesta
            : null;
    });

    const editando = computed(() =>
        accion.value === "editar" ? detalle.value : null,
    );

    const borrando = computed(() =>
        accion.value === "eliminar" ? detalle.value : null,
    );

    // El modal de borrado se abre en cuanto se pulsa, aunque la frase todavía
    // no haya llegado: cerrarse y abrirse solo al contestar el servidor se ve
    // como un parpadeo. Mientras tanto el botón de eliminar está deshabilitado.
    const borradoAbierto = computed(() => accion.value === "eliminar");

    function pedirDetalle(fila) {
        seleccionada.value = fila.id;

        router.reload({
            only: ["novedad"],
            data: { novedad: fila.id },
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function abrirEdicion(fila) {
        accion.value = "editar";
        pedirDetalle(fila);
    }

    function abrirBorrado(fila) {
        accion.value = "eliminar";
        pedirDetalle(fila);
    }

    function cerrar() {
        accion.value = null;
        seleccionada.value = null;
        enviando.value = false;
    }

    // Los valores del registro se ponen en el mismo formulario del registro:
    // uno solo, para que los campos y sus errores se comporten igual en las dos
    // acciones.
    watch(editando, (detalleDeLaNovedad) => {
        if (!detalleDeLaNovedad) {
            return;
        }

        form.clearErrors();

        Object.entries(detalleDeLaNovedad.valores).forEach(([campo, valor]) => {
            form[campo] = valor;
        });
    });

    function guardarEdicion() {
        if (!editando.value) {
            return;
        }

        form.put(`${ruta}/${editando.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                cerrar();
                form.reset();
            },
        });
    }

    function confirmarBorrado() {
        if (!borrando.value) {
            return;
        }

        enviando.value = true;

        router.delete(`${ruta}/${borrando.value.id}`, {
            preserveScroll: true,
            onFinish: cerrar,
        });
    }

    return {
        editando,
        borrando,
        borradoAbierto,
        enviando,
        abrirEdicion,
        abrirBorrado,
        cerrar,
        guardarEdicion,
        confirmarBorrado,
    };
}
