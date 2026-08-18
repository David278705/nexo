import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import Modal from "./Modal.vue";

function montar(props = {}) {
    return mount(Modal, {
        props: { abierto: true, titulo: "Registrar una incapacidad", ...props },
        attachTo: document.body,
    });
}

describe("Modal", () => {
    it("no pinta nada mientras está cerrado", () => {
        montar({ abierto: false });

        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    });

    it("se anuncia como diálogo con su título", () => {
        const componente = montar();
        const dialogo = document.body.querySelector('[role="dialog"]');

        expect(dialogo.getAttribute("aria-modal")).toBe("true");
        expect(dialogo.getAttribute("aria-label")).toBe(
            "Registrar una incapacidad",
        );

        componente.unmount();
    });

    it("se cierra con Escape", async () => {
        const componente = montar();

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));

        expect(componente.emitted("cerrar")).toHaveLength(1);

        componente.unmount();
    });

    it("usa 800 px para el registro de novedades y 560 px para lo corto", () => {
        const corto = montar();
        expect(document.body.innerHTML).toContain("sm:max-w-modal-corto");
        corto.unmount();

        const ancho = montar({ ancho: "ancho" });
        expect(document.body.innerHTML).toContain("sm:max-w-modal-ancho");
        ancho.unmount();
    });
});
