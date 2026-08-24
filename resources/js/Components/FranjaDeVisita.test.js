import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const enviar = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    router: { post: (...argumentos) => enviar(...argumentos) },
}));

const FranjaDeVisita = (await import("./FranjaDeVisita.vue")).default;

const visita = {
    empresa: "Distribuidora El Progreso S.A.S.",
    modo: "Modo consulta",
    tono: "informacion",
    explicacion:
        "Estás dentro como proveedor, de solo lectura. Tu entrada quedó registrada en la auditoría de esta empresa.",
    motivo: "Revisar el cálculo de vacaciones que reportó Ana María.",
    salir: "Salir del entorno",
};

function montar(cambios = {}) {
    return mount(FranjaDeVisita, {
        props: { visita: { ...visita, ...cambios } },
        attachTo: document.body,
    });
}

describe("la franja de la visita al entorno del cliente", () => {
    it("dice de quién son los datos, en qué modo se entró y por qué", () => {
        const franja = montar();

        expect(franja.text()).toContain("Distribuidora El Progreso S.A.S.");
        expect(franja.text()).toContain("Modo consulta");
        expect(franja.text()).toContain("de solo lectura");
        expect(franja.text()).toContain("Revisar el cálculo de vacaciones");
    });

    it("distingue el modo edición del de consulta sin depender solo del color", () => {
        const consulta = montar();
        const edicion = montar({ modo: "Modo edición", tono: "advertencia" });

        expect(consulta.text()).toContain("Modo consulta");
        expect(edicion.text()).toContain("Modo edición");
        expect(edicion.html()).toContain("advertencia");
    });

    it("sale con un POST, no con un enlace", async () => {
        const franja = montar();

        await franja.find("button").trigger("click");

        expect(enviar).toHaveBeenCalledWith("/entorno/salir");
    });
});
