import { afterEach, describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import Selector from "./Selector.vue";

function opciones(cantidad) {
    return Array.from({ length: cantidad }, (_, indice) => ({
        valor: `v${indice}`,
        texto: `Opción ${indice}`,
    }));
}

function opcionesVisibles(componente) {
    return componente.findAll('[role="option"]').map((el) => el.text());
}

afterEach(() => {
    document.body.innerHTML = "";
});

describe("Selector, diez opciones o menos", () => {
    it("no muestra el combobox de búsqueda: un select nativo basta", () => {
        const componente = mount(Selector, {
            props: { etiqueta: "Cargo", opciones: opciones(10) },
        });

        expect(componente.find('[role="combobox"]').exists()).toBe(false);
        expect(componente.find("select").exists()).toBe(true);
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

/*
 * Más de diez opciones (documento 3, 3.4): un solo combobox, no una caja de
 * búsqueda que filtra un `<select>` aparte que hay que volver a abrir. Se
 * escribe, la lista de resultados aparece debajo del mismo campo, se navega
 * con las flechas y se elige con Enter o con el clic.
 */
describe("Selector, más de diez opciones — el combobox de una sola caja", () => {
    function montar(propsExtra = {}) {
        return mount(Selector, {
            props: {
                etiqueta: "Ciudad",
                opciones: [
                    ...opciones(10),
                    { valor: "bog", texto: "Bogotá" },
                    { valor: "med", texto: "Medellín" },
                ],
                ...propsExtra,
            },
            attachTo: document.body,
        });
    }

    it("muestra un único campo de texto, no una caja y un select aparte", () => {
        const componente = montar();

        expect(componente.find('[role="combobox"]').exists()).toBe(true);
        expect(componente.find("select").exists()).toBe(false);
    });

    it("la lista de resultados no se ve hasta que se abre el campo", () => {
        const componente = montar();

        expect(componente.find('[role="listbox"]').exists()).toBe(false);
    });

    it("al escribir aparece la lista debajo, filtrada, sin distinguir mayúsculas", async () => {
        const componente = montar();
        const campo = componente.find('[role="combobox"]');

        await campo.trigger("focus");
        await campo.setValue("bogo");
        await campo.trigger("input");

        expect(opcionesVisibles(componente)).toEqual(["Bogotá"]);
    });

    it("elegir una opción con el clic la escribe en la caja y cierra la lista", async () => {
        const componente = montar();
        const campo = componente.find('[role="combobox"]');

        await campo.trigger("focus");
        await campo.setValue("bogo");
        await campo.trigger("input");
        await componente.find('[role="option"]').trigger("mousedown");

        expect(componente.emitted("update:modelValue")[0]).toEqual(["bog"]);
        expect(campo.element.value).toBe("Bogotá");
        expect(componente.find('[role="listbox"]').exists()).toBe(false);
    });

    it("las flechas mueven el resaltado y Enter elige la opción resaltada", async () => {
        const componente = montar({ modelValue: "" });
        const campo = componente.find('[role="combobox"]');

        await campo.trigger("focus");
        await campo.setValue("mun");
        await campo.trigger("input"); // Sin coincidencias todavía: usa "medel".

        await campo.setValue("medel");
        await campo.trigger("input");
        await campo.trigger("keydown", { key: "ArrowDown" });
        await campo.trigger("keydown", { key: "Enter" });

        expect(componente.emitted("update:modelValue")[0]).toEqual(["med"]);
    });

    it("Escape cierra la lista y devuelve la caja al texto de lo ya elegido", async () => {
        const componente = montar({ modelValue: "bog" });
        const campo = componente.find('[role="combobox"]');

        await campo.trigger("focus");
        await campo.setValue("algo que no calza");
        await campo.trigger("input");
        await campo.trigger("keydown", { key: "Escape" });

        expect(campo.element.value).toBe("Bogotá");
        expect(componente.find('[role="listbox"]').exists()).toBe(false);
    });

    it("perder el foco sin elegir nada revierte la caja al valor ya elegido", async () => {
        const componente = montar({ modelValue: "bog" });
        const campo = componente.find('[role="combobox"]');

        await campo.trigger("focus");
        await campo.setValue("medel");
        await campo.trigger("input");
        await campo.trigger("blur");

        expect(campo.element.value).toBe("Bogotá");
    });

    it("tiene etiqueta real asociada al combobox", () => {
        const componente = montar();

        expect(componente.find("label").attributes("for")).toBe(
            componente.find('[role="combobox"]').attributes("id"),
        );
    });

    it("anuncia la lista con los atributos ARIA de un combobox", async () => {
        const componente = montar();
        const campo = componente.find('[role="combobox"]');

        expect(campo.attributes("aria-expanded")).toBe("false");

        await campo.trigger("focus");

        expect(campo.attributes("aria-expanded")).toBe("true");
        expect(campo.attributes("aria-controls")).toBe(
            componente.find('[role="listbox"]').attributes("id"),
        );
    });
});
