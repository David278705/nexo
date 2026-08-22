import { beforeEach, describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import ModalDeBorrado from "./ModalDeBorrado.vue";

/*
 * La confirmación de borrado (documento 3, 2.4 y 6.3). Lo que hay que
 * demostrar es que el componente **no escribe el mensaje**: lo enseña tal cual
 * llega del servidor, y no deja eliminar mientras no haya llegado.
 */
const MENSAJE =
    "Vas a eliminar las vacaciones del 1 al 8 de julio de Carlos Ramírez. " +
    "Se eliminarán 8 días de vacaciones y el saldo de Carlos Ramírez pasará de 4,25 a 12,25 días. " +
    "Esta acción queda registrada en la auditoría.";

function montar(props = {}) {
    return mount(ModalDeBorrado, {
        props: { abierto: true, titulo: "Eliminar vacaciones", ...props },
        attachTo: document.body,
    });
}

function boton(texto) {
    return [...document.body.querySelectorAll("button")].find(
        (candidato) => candidato.textContent.trim() === texto,
    );
}

describe("la confirmación de borrado", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("enseña la frase del servidor entera, con la consecuencia calculada", () => {
        const componente = montar({ mensaje: MENSAJE });

        expect(document.body.textContent).toContain(MENSAJE);
        expect(document.body.textContent).toContain("Eliminar vacaciones");
        // Nunca un «¿está seguro?» (documento 3, 2.5).
        expect(document.body.textContent).not.toContain("seguro");

        componente.unmount();
    });

    it("no deja eliminar mientras la consecuencia no ha llegado", async () => {
        const componente = montar({ mensaje: "" });

        expect(boton("Eliminar").disabled).toBe(true);

        boton("Eliminar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(componente.emitted("confirmar")).toBeUndefined();

        componente.unmount();
    });

    it("avisa a la pantalla al confirmar y al cancelar", async () => {
        const componente = montar({ mensaje: MENSAJE });

        boton("Eliminar").dispatchEvent(new Event("click"));
        boton("Cancelar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(componente.emitted("confirmar")).toHaveLength(1);
        expect(componente.emitted("cerrar")).toHaveLength(1);

        componente.unmount();
    });

    it("deshabilita el botón mientras se está eliminando", () => {
        const componente = montar({ mensaje: MENSAJE, enviando: true });

        expect(boton("Eliminar").disabled).toBe(true);

        componente.unmount();
    });
});
