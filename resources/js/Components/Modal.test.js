import { afterEach, describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import { h, nextTick } from "vue";
import Modal from "./Modal.vue";

function montar(props = {}, slots = {}) {
    return mount(Modal, {
        props: { abierto: true, titulo: "Registrar una incapacidad", ...props },
        slots,
        attachTo: document.body,
    });
}

function tab({ atras = false } = {}) {
    const evento = new KeyboardEvent("keydown", {
        key: "Tab",
        shiftKey: atras,
        cancelable: true,
    });
    document.dispatchEvent(evento);
    return evento;
}

afterEach(() => {
    document.body.innerHTML = "";
});

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
        expect(document.body.innerHTML).toContain("lg:max-w-modal-corto");
        corto.unmount();

        const ancho = montar({ ancho: "ancho" });
        expect(document.body.innerHTML).toContain("lg:max-w-modal-ancho");
        ancho.unmount();
    });

    it("ocupa la pantalla completa hasta 1024px, no hasta 640px", () => {
        const componente = montar();

        expect(document.body.innerHTML).toContain("lg:h-auto");
        expect(document.body.innerHTML).not.toContain("sm:h-auto");

        componente.unmount();
    });

    it("al abrir pone el foco dentro del diálogo si nadie más lo hizo", async () => {
        const componente = montar({}, { pie: () => h("button", "Guardar") });
        await nextTick();

        const dialogo = document.body.querySelector('[role="dialog"]');
        expect(dialogo.contains(document.activeElement)).toBe(true);

        componente.unmount();
    });

    it("atrapa el tabulador: del último campo vuelve al primero", async () => {
        const componente = montar(
            {},
            { pie: () => h("button", { id: "guardar" }, "Guardar") },
        );
        await nextTick();

        document.getElementById("guardar").focus();
        const evento = tab();

        expect(evento.defaultPrevented).toBe(true);
        expect(document.activeElement.getAttribute("aria-label")).toBe("Cerrar");

        componente.unmount();
    });

    it("atrapa el tabulador hacia atrás: del primero vuelve al último", async () => {
        const componente = montar(
            {},
            { pie: () => h("button", { id: "guardar" }, "Guardar") },
        );
        await nextTick();

        document.body.querySelector('[aria-label="Cerrar"]').focus();
        const evento = tab({ atras: true });

        expect(evento.defaultPrevented).toBe(true);
        expect(document.activeElement.id).toBe("guardar");

        componente.unmount();
    });

    it("si el foco escapa del diálogo, el siguiente tabulador lo trae de vuelta", async () => {
        const fuera = document.createElement("button");
        fuera.textContent = "fuera";
        document.body.appendChild(fuera);
        fuera.focus();

        const componente = montar(
            {},
            { pie: () => h("button", { id: "guardar" }, "Guardar") },
        );
        await nextTick();

        // El foco sigue en `fuera` porque el slot no puso nada con
        // autoenfoque; el modal solo pone el primero de reserva si nadie
        // adentro ya lo tenía, y aquí nadie del diálogo lo pidió.
        fuera.focus();
        tab();

        expect(document.body.querySelector('[role="dialog"]').contains(document.activeElement)).toBe(true);

        componente.unmount();
        fuera.remove();
    });

    it("al cerrar devuelve el foco a quien abrió el modal", async () => {
        const disparador = document.createElement("button");
        disparador.textContent = "Nuevo";
        document.body.appendChild(disparador);
        disparador.focus();

        const componente = mount(Modal, {
            props: { abierto: false, titulo: "Nueva incapacidad" },
            attachTo: document.body,
        });

        await componente.setProps({ abierto: true });
        await nextTick();
        await componente.setProps({ abierto: false });

        expect(document.activeElement).toBe(disparador);

        componente.unmount();
        disparador.remove();
    });
});
