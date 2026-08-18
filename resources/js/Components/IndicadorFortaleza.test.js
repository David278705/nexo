import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import IndicadorFortaleza from "./IndicadorFortaleza.vue";

describe("IndicadorFortaleza", () => {
    it("no muestra nada mientras el campo está vacío", () => {
        const componente = mount(IndicadorFortaleza, {
            props: { contrasena: "" },
        });

        expect(componente.text()).toBe("");
    });

    it("califica de muy débil una contraseña que no llega al mínimo", () => {
        const componente = mount(IndicadorFortaleza, {
            props: { contrasena: "Ab1$" },
        });

        expect(componente.text()).toContain("Muy débil");
    });

    it("califica de fuerte una contraseña larga con letras, números y símbolos", () => {
        const componente = mount(IndicadorFortaleza, {
            props: { contrasena: "Contrasena9$egura" },
        });

        expect(componente.text()).toContain("Fuerte");
    });

    it("dice la fortaleza con palabras y no solo con color", () => {
        const componente = mount(IndicadorFortaleza, {
            props: { contrasena: "abcdefg1" },
        });

        expect(componente.text()).toContain("Fortaleza:");
    });
});
