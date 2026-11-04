import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import CampoDinero from "./CampoDinero.vue";

function montar(props = {}) {
    return mount(CampoDinero, {
        props: { etiqueta: "Monto recibido", modelValue: "", ...props },
    });
}

describe("CampoDinero", () => {
    it("envía trescientos ochenta mil al escribir «380.000», no 380", async () => {
        const campo = montar();

        await campo.find("input").setValue("380.000");

        expect(campo.emitted("update:modelValue").at(-1)).toEqual(["380000"]);
    });

    it("no usa un campo number, que es el que se comía los miles", () => {
        expect(montar().find("input").attributes("type")).toBe("text");
    });

    it("pide el teclado numérico en el celular", () => {
        expect(montar().find("input").attributes("inputmode")).toBe("decimal");
    });

    it("pone en limpio lo escrito al salir del campo", async () => {
        const campo = montar();
        const caja = campo.find("input");

        await caja.setValue("1750905");
        await caja.trigger("blur");

        expect(caja.element.value).toBe("$ 1.750.905");
        expect(campo.emitted("salir").at(-1)).toEqual(["1750905"]);
    });

    it("enseña formateado el valor que llega del servidor", () => {
        expect(montar({ modelValue: "380000.00" }).find("input").element.value).toBe(
            "$ 380.000",
        );
    });

    it("se vacía cuando el formulario se reinicia", async () => {
        const campo = montar({ modelValue: "380000" });

        await campo.setProps({ modelValue: "" });

        expect(campo.find("input").element.value).toBe("");
    });

    it("no reformatea bajo el cursor mientras se escribe", async () => {
        const campo = montar();
        const caja = campo.find("input");

        await caja.setValue("380.0");
        await campo.setProps({ modelValue: "3800" });

        expect(caja.element.value).toBe("380.0");
    });
});
