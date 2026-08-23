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

    /*
     * El bloque de acción (F4-03). La pantalla no cuenta nada: pinta lo que el
     * servidor le manda, y cada tarjeta es un enlace al listado ya filtrado.
     */
    it("pinta una tarjeta por cada acción que llega del servidor", () => {
        const componente = mount(Inicio, {
            props: {
                fecha: "Lunes, 17 de agosto de 2026",
                acciones: [
                    {
                        valor: 2,
                        etiqueta: "Contratos vencidos sin gestionar",
                        enlace: "/contratos?vence=todos&estado=vencido",
                        accion: "Ver y gestionar",
                        tono: "error",
                        extras: [],
                    },
                    {
                        valor: 3,
                        etiqueta: "Contratos vencen en 30 días",
                        enlace: "/contratos?vence=30",
                        accion: "Ver y renovar",
                        tono: "",
                        extras: [
                            { texto: "7 en 60 días", enlace: "/contratos?vence=60" },
                        ],
                    },
                ],
            },
        });

        const destinos = componente
            .findAll("section a")
            .map((enlace) => enlace.attributes("href"));

        expect(destinos).toEqual([
            "/contratos?vence=todos&estado=vencido",
            "/contratos?vence=30",
            "/contratos?vence=60",
        ]);
    });

    it("dice que no hay nada pendiente en vez de dejar el bloque vacío", () => {
        const componente = mount(Inicio, {
            props: {
                fecha: "Lunes, 17 de agosto de 2026",
                acciones: [],
                accionesDiferidas: [],
            },
        });

        expect(componente.find("section a").exists()).toBe(false);
        expect(componente.text()).toContain("Nada exige tu atención hoy");
    });

    /*
     * La tarjeta que tarda llega en la segunda vuelta de Inertia. Mientras no
     * está, la pantalla no puede afirmar que no hay nada pendiente: decirlo y
     * desdecirse un segundo después es peor que no decir nada.
     */
    it("no dice que no hay nada mientras la tarjeta diferida no ha llegado", () => {
        const componente = mount(Inicio, {
            props: { fecha: "Lunes, 17 de agosto de 2026", acciones: [] },
        });

        expect(componente.text()).not.toContain("Nada exige tu atención hoy");
    });

    it("suma la tarjeta diferida a las demás en cuanto llega", () => {
        const componente = mount(Inicio, {
            props: {
                fecha: "Lunes, 17 de agosto de 2026",
                acciones: [],
                accionesDiferidas: [
                    {
                        valor: 4,
                        etiqueta: "Personas con 15 días o más de vacaciones acumuladas",
                        enlace: "/empleados?atencion=vacaciones-acumuladas",
                        accion: "Ver empleados",
                        tono: "",
                        extras: [],
                    },
                ],
            },
        });

        expect(componente.find("section a").attributes("href")).toBe(
            "/empleados?atencion=vacaciones-acumuladas",
        );
    });
});
