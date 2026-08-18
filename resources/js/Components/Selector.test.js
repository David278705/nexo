import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import Selector from "./Selector.vue";

function opciones(cantidad) {
    return Array.from({ length: cantidad }, (_, indice) => ({
        valor: `v${indice}`,
        texto: `Opción ${indice}`,
    }));
}

describe("Selector", () => {
    it("no muestra búsqueda con diez opciones o menos", () => {
        const componente = mount(Selector, {
            props: { etiqueta: "Cargo", opciones: opciones(10) },
        });

        expect(componente.find('input[type="search"]').exists()).toBe(false);
    });

    it("muestra búsqueda a partir de once opciones", () => {
        const componente = mount(Selector, {
            props: { etiqueta: "Ciudad", opciones: opciones(11) },
        });

        expect(componente.find('input[type="search"]').exists()).toBe(true);
    });

    it("filtra las opciones sin distinguir mayúsculas", async () => {
        const componente = mount(Selector, {
            props: {
                etiqueta: "Ciudad",
                opciones: [
                    ...opciones(10),
                    { valor: "bog", texto: "Bogotá" },
                    { valor: "med", texto: "Medellín" },
                ],
            },
        });

        await componente.find('input[type="search"]').setValue("bogo");

        // La opción del marcador siempre queda, más la única coincidencia.
        expect(componente.findAll("option")).toHaveLength(2);
        expect(componente.text()).toContain("Bogotá");
    });

    it("avisa del valor elegido", async () => {
        const componente = mount(Selector, {
            props: {
                etiqueta: "Tipo de contrato",
                opciones: [{ valor: "fijo", texto: "Término fijo" }],
            },
        });

        await componente.find("select").setValue("fijo");

        expect(componente.emitted("update:modelValue")[0]).toEqual(["fijo"]);
    });

    it("tiene etiqueta real asociada al control", () => {
        const componente = mount(Selector, {
            props: { etiqueta: "Cargo", opciones: opciones(3) },
        });

        expect(componente.find("label").attributes("for")).toBe(
            componente.find("select").attributes("id"),
        );
    });
});
