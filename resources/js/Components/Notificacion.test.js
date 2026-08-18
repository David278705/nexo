import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import Notificacion from "./Notificacion.vue";

describe("Notificacion", () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it("no aparece sin mensaje", () => {
        const componente = mount(Notificacion, { props: { mensaje: "" } });

        expect(document.body.textContent).not.toContain("Se guardó");

        componente.unmount();
    });

    it("aparece con el mensaje y se anuncia al lector de pantalla", async () => {
        const componente = mount(Notificacion, {
            props: { mensaje: "Se guardó la incapacidad." },
        });
        await componente.vm.$nextTick();

        const aviso = document.body.querySelector('[role="status"]');

        expect(aviso.textContent).toContain("Se guardó la incapacidad.");
        expect(aviso.getAttribute("aria-live")).toBe("polite");

        componente.unmount();
    });

    it("desaparece sola a los cuatro segundos", async () => {
        const componente = mount(Notificacion, {
            props: { mensaje: "Se guardó la incapacidad." },
        });
        await componente.vm.$nextTick();

        vi.advanceTimersByTime(4000);
        await componente.vm.$nextTick();

        expect(document.body.querySelector('[role="status"]')).toBeNull();
        expect(componente.emitted("cerrar")).toHaveLength(1);

        componente.unmount();
    });
});
