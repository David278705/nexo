import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import AvisoDeReingreso from "./AvisoDeReingreso.vue";

/*
 * Los textos de estas pruebas son de mentira a propósito: los de verdad son
 * constantes del servidor y quien los defiende palabra por palabra es
 * `AdvertenciaDeReingresoTest`. Aquí solo se comprueba que lo que llega se
 * pinta, y que el énfasis de los 30 días se nota.
 */
const reingreso = {
    texto: "Esta persona ya tuvo una vinculación anterior con la empresa.",
    nota: "Usted recibirá las alertas de periodo de prueba estipuladas.",
    nombre: "Carolina Restrepo",
    vinculaciones: 1,
    reciente: false,
    detalle: "La vinculación anterior terminó el 31 de julio de 2026.",
};

function montar(cambios = {}) {
    return mount(AvisoDeReingreso, {
        props: { reingreso: { ...reingreso, ...cambios } },
    });
}

describe("Aviso de reingreso", () => {
    it("no pinta nada cuando no hay reingreso que advertir", () => {
        const componente = mount(AvisoDeReingreso, {
            props: { reingreso: null },
        });

        expect(componente.text()).toBe("");
    });

    it("muestra la advertencia y su nota", () => {
        const texto = montar().text();

        expect(texto).toContain(reingreso.texto);
        expect(texto).toContain(reingreso.nota);
        expect(texto).toContain(reingreso.detalle);
    });

    it("avisa en palabras, no solo en color, cuando el reingreso es reciente", () => {
        expect(montar({ reciente: true }).text()).toContain(
            "Reingreso dentro de los 30 días siguientes a la salida",
        );
    });

    it("resalta el detalle solo cuando el reingreso es reciente", () => {
        // El tercer párrafo es el detalle: título, texto, detalle y nota.
        const detalleDe = (componente) => componente.findAll("p")[2];

        expect(detalleDe(montar()).classes()).not.toContain("font-semibold");
        expect(detalleDe(montar({ reciente: true })).classes()).toContain(
            "font-semibold",
        );
    });

    it("no pinta la línea de detalle cuando no hay fecha de salida", () => {
        expect(montar({ detalle: null }).text()).toContain(reingreso.texto);
        expect(montar({ detalle: null }).text()).not.toContain("terminó el");
    });
});
