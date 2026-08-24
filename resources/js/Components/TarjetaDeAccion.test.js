import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const TarjetaDeAccion = (await import("./TarjetaDeAccion.vue")).default;

const props = {
    valor: 3,
    etiqueta: "Contratos vencen en 30 días",
    enlace: "/contratos?vence=30",
    accion: "Ver y renovar",
};

describe("TarjetaDeAccion", () => {
    it("es un enlace al listado filtrado, que es lo que la separa de un indicador", () => {
        const enlace = mount(TarjetaDeAccion, { props }).find("a");

        expect(enlace.attributes("href")).toBe("/contratos?vence=30");
        expect(enlace.text()).toContain("Ver y renovar");
    });

    it("enseña la cifra y su etiqueta tal como llegan del servidor", () => {
        const tarjeta = mount(TarjetaDeAccion, { props });

        expect(tarjeta.text()).toContain("3");
        expect(tarjeta.text()).toContain("Contratos vencen en 30 días");
    });

    it("da a cada cifra acumulada su propio destino", () => {
        const enlaces = mount(TarjetaDeAccion, {
            props: {
                ...props,
                extras: [
                    { texto: "7 en 60 días", enlace: "/contratos?vence=60" },
                    { texto: "9 en 90 días", enlace: "/contratos?vence=90" },
                ],
            },
        }).findAll("a");

        expect(enlaces).toHaveLength(3);
        expect(enlaces[1].attributes("href")).toBe("/contratos?vence=60");
        expect(enlaces[2].text()).toBe("9 en 90 días");
    });

    it("no repite la acción cuando ya lleva cifras acumuladas debajo", () => {
        const tarjeta = mount(TarjetaDeAccion, {
            props: {
                ...props,
                extras: [{ texto: "7 en 60 días", enlace: "/contratos?vence=60" }],
            },
        });

        expect(tarjeta.text()).not.toContain("Ver y renovar");
    });

    /*
     * El rojo solo cuando algo está mal de verdad, y nunca como único portador
     * de la información: la etiqueta ya dice «vencidos sin gestionar».
     */
    it("solo se pinta de rojo cuando le pasan el tono de error", () => {
        const normal = mount(TarjetaDeAccion, { props });
        const grave = mount(TarjetaDeAccion, {
            props: { ...props, tono: "error" },
        });

        expect(normal.html()).not.toContain("error");
        expect(grave.html()).toContain("bg-error-fondo");
    });

    /*
     * El verde es del bloque de atención del panel del proveedor (F5-06,
     * documento 4, 3.1): las tarjetas que informan que algo que se vigila
     * está bien, como el proceso diario que sí corrió.
     */
    it("se pinta de verde cuando le pasan el tono de éxito", () => {
        const bien = mount(TarjetaDeAccion, { props: { ...props, tono: "exito" } });

        expect(bien.html()).toContain("bg-exito-fondo");
    });

    it("acepta un valor de texto, no solo un conteo", () => {
        const tarjeta = mount(TarjetaDeAccion, {
            props: { ...props, valor: "OK" },
        });

        expect(tarjeta.text()).toContain("OK");
    });
});
