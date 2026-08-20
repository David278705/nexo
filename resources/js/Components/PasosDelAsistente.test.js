import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import PasosDelAsistente from "./PasosDelAsistente.vue";

const titulos = ["Identificación", "Residencia", "Contrato", "Resumen"];

function montar(actual = 1) {
    return mount(PasosDelAsistente, { props: { titulos, actual } });
}

describe("Pasos del asistente", () => {
    it("enseña los cuatro pasos desde el principio, no solo el actual", () => {
        const texto = montar(0).text();

        titulos.forEach((titulo) => expect(texto).toContain(titulo));
    });

    it("marca el paso actual para quien no distingue el color", () => {
        expect(montar(1).findAll("li")[1].attributes("aria-current")).toBe("step");
        expect(montar(1).findAll("li")[0].attributes("aria-current")).toBeUndefined();
    });

    it("deja volver a un paso ya recorrido", async () => {
        const componente = montar(2);

        await componente.findAll("button")[0].trigger("click");

        expect(componente.emitted("ir")[0]).toEqual([0]);
    });

    it("no deja saltar a un paso que todavía no se ha llenado", () => {
        const componente = montar(1);

        expect(componente.findAll("button")[2].attributes("disabled")).toBeDefined();
        expect(componente.findAll("button")[3].attributes("disabled")).toBeDefined();
    });
});
