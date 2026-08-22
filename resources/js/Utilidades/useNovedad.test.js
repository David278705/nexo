import { beforeEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { nextTick, ref } from "vue";

const reload = vi.fn();
const eliminar = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    router: { reload, delete: eliminar },
}));

const { useNovedad } = await import("./useNovedad");

/**
 * Un `useForm` de mentira: de él solo interesan los campos, `clearErrors()` y
 * `put()`. La biblioteca ya está probada por su autor.
 */
function formulario() {
    return {
        vinculacion_id: null,
        fecha_inicio: "",
        fecha_fin: "",
        clearErrors: vi.fn(),
        reset: vi.fn(),
        put: vi.fn(),
    };
}

const DETALLE = {
    id: 12,
    datos: [],
    valores: {
        vinculacion_id: 7,
        fecha_inicio: "2026-07-27",
        fecha_fin: "2026-07-31",
    },
    confirmacion:
        "Vas a eliminar las vacaciones del 27 al 31 de julio de Carlos Ramírez. " +
        "Esta acción queda registrada en la auditoría.",
};

function montar() {
    const novedad = ref(null);
    const form = formulario();

    return {
        novedad,
        form,
        ...useNovedad({ ruta: "/vacaciones", form, novedad: () => novedad.value }),
    };
}

describe("editar y eliminar una novedad", () => {
    beforeEach(() => {
        reload.mockClear();
        eliminar.mockClear();
    });

    it("pide el detalle de la fila con una recarga parcial, sin traerlo en el listado", () => {
        const { abrirEdicion } = montar();

        abrirEdicion({ id: 12 });

        expect(reload).toHaveBeenCalledTimes(1);
        expect(reload.mock.calls[0][0].only).toEqual(["novedad"]);
        expect(reload.mock.calls[0][0].data).toEqual({ novedad: 12 });
    });

    it("rellena el formulario con los valores del servidor cuando el detalle llega", async () => {
        const { novedad, form, abrirEdicion, editando } = montar();

        abrirEdicion({ id: 12 });
        novedad.value = DETALLE;
        await nextTick();

        expect(editando.value).toEqual(DETALLE);
        expect(form.fecha_inicio).toBe("2026-07-27");
        expect(form.fecha_fin).toBe("2026-07-31");
        expect(form.vinculacion_id).toBe(7);
    });

    it("no da por bueno el detalle de otra fila", async () => {
        const { novedad, abrirBorrado, borrando } = montar();

        abrirBorrado({ id: 12 });
        // Llega, tarde, la respuesta de la fila anterior.
        novedad.value = { ...DETALLE, id: 9 };
        await nextTick();

        expect(borrando.value).toBeNull();
    });

    it("abre la confirmación en cuanto se pulsa, aunque la frase todavía no esté", async () => {
        const { novedad, abrirBorrado, borradoAbierto, borrando } = montar();

        abrirBorrado({ id: 12 });
        await nextTick();

        expect(borradoAbierto.value).toBe(true);
        expect(borrando.value).toBeNull();

        novedad.value = DETALLE;
        await nextTick();

        expect(borrando.value.confirmacion).toContain("Vas a eliminar");
    });

    it("guarda la edición sobre la dirección del registro", async () => {
        const { novedad, form, abrirEdicion, guardarEdicion } = montar();

        abrirEdicion({ id: 12 });
        novedad.value = DETALLE;
        await nextTick();

        guardarEdicion();

        expect(form.put).toHaveBeenCalledWith("/vacaciones/12", expect.anything());
    });

    it("elimina con el verbo y la dirección del registro", async () => {
        const { novedad, abrirBorrado, confirmarBorrado } = montar();

        abrirBorrado({ id: 12 });
        novedad.value = DETALLE;
        await nextTick();

        confirmarBorrado();

        expect(eliminar).toHaveBeenCalledTimes(1);
        expect(eliminar.mock.calls[0][0]).toBe("/vacaciones/12");
    });

    it("no elimina nada mientras el detalle no ha llegado", () => {
        const { abrirBorrado, confirmarBorrado } = montar();

        abrirBorrado({ id: 12 });
        confirmarBorrado();

        expect(eliminar).not.toHaveBeenCalled();
    });

    /*
     * Lo mismo que comprueba `useListado.test.js`: que las tres pantallas usen
     * el composable y no vuelvan a escribir su copia.
     */
    const PANTALLAS = [
        "resources/js/Pages/Incapacidades/Index.vue",
        "resources/js/Pages/Vacaciones/Index.vue",
        "resources/js/Pages/Licencias/Index.vue",
    ];

    it.each(PANTALLAS)("%s usa el composable y el modal compartido", (ruta) => {
        const codigo = readFileSync(ruta, "utf8");

        expect(codigo).toContain("useNovedad");
        expect(codigo).toContain("ModalDeBorrado");
        expect(codigo).not.toContain("function abrirEdicion(");
        expect(codigo).not.toContain("function confirmarBorrado(");
    });
});
