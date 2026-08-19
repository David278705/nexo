import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import Paginacion from "./Paginacion.vue";

const props = {
    pagina: 2,
    paginas: 3,
    total: 68,
    desde: 26,
    hasta: 50,
    porPagina: 25,
};

describe("Paginacion", () => {
    it("dice qué tramo del total se está viendo", () => {
        const componente = mount(Paginacion, { props });

        expect(componente.text()).toContain("Mostrando 26 a 50 de 68");
    });

    it("ofrece los tres tamaños de página del documento", () => {
        const componente = mount(Paginacion, { props });
        const opciones = componente.findAll("option").map((o) => o.text());

        expect(opciones).toEqual(["25", "50", "100"]);
    });

    it("pide la página anterior y la siguiente sin calcularlas por su cuenta", async () => {
        const componente = mount(Paginacion, { props });
        const botones = componente.findAll("button");

        await botones[0].trigger("click");
        await botones[1].trigger("click");

        expect(componente.emitted("ir")).toEqual([[1], [3]]);
    });

    it("no deja retroceder desde la primera ni avanzar desde la última", () => {
        const primera = mount(Paginacion, { props: { ...props, pagina: 1 } });
        const ultima = mount(Paginacion, { props: { ...props, pagina: 3 } });

        expect(primera.findAll("button")[0].attributes("disabled")).toBeDefined();
        expect(ultima.findAll("button")[1].attributes("disabled")).toBeDefined();
    });

    it("avisa del tamaño elegido como número, no como texto", async () => {
        const componente = mount(Paginacion, { props });

        await componente.find("select").setValue("100");

        expect(componente.emitted("tamano")).toEqual([[100]]);
    });
});
