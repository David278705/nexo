import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/",
    props: {
        usuario: {
            nombre: "María Fernanda Gutiérrez",
            correo: "maria@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                { clave: "inicio", etiqueta: "Inicio", grupo: null, url: "/" },
            ],
        },
    },
};

/*
 * `Head` necesita el gestor de encabezado que instala `createInertiaApp`, y
 * `Link` el router. Ninguno existe fuera del navegador.
 */
vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { post: vi.fn() },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Inicio = (await import("./Inicio.vue")).default;

describe("Inicio", () => {
    it("saluda por el nombre de pila, no por el nombre completo", () => {
        const saludo = mount(Inicio, {
            props: { fecha: "Lunes, 17 de agosto de 2026" },
        }).find("h1");

        expect(saludo.text()).toBe("Hola, María");
    });

    it("muestra la fecha tal como llega del servidor", () => {
        const componente = mount(Inicio, {
            props: { fecha: "Lunes, 17 de agosto de 2026" },
        });

        expect(componente.text()).toContain("Lunes, 17 de agosto de 2026");
    });

    it("se monta dentro del armazón, con su menú", () => {
        const componente = mount(Inicio, {
            props: { fecha: "Lunes, 17 de agosto de 2026" },
        });

        expect(componente.find('nav[aria-label="Secciones"]').exists()).toBe(true);
    });
});
