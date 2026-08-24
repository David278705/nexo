import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            { clave: "inicio", etiqueta: "Inicio", grupo: null, url: "/proveedor" },
        ],
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
    usePage: () => pagina,
}));

const Inicio = (await import("./Inicio.vue")).default;

const base = {
    fecha: "Lunes, 24 de agosto de 2026",
    atencion: [
        {
            valor: "2",
            etiqueta: "Clientes en riesgo — el uso cayó de forma marcada",
            enlace: "/proveedor/empresas",
            accion: "Ver cuáles y llamarlos",
            tono: "error",
        },
        {
            valor: "OK",
            etiqueta: "El proceso de alertas corrió hoy sin problemas",
            enlace: "/proveedor/salud",
            accion: "Ver salud del sistema",
            tono: "exito",
        },
    ],
    negocio: [
        {
            valor: "12",
            etiqueta: "Empresas activas",
            variacion: "+ 2 este mes",
            tendencia: "sube",
            nota: "",
            notaTono: "",
        },
        {
            valor: "$ 4.180.000",
            etiqueta: "Ingreso recurrente mensual",
            variacion: "",
            tendencia: "",
            nota: "Primer mes con historial: todavía no hay con qué comparar.",
            notaTono: "",
        },
    ],
};

function montar(props = {}) {
    return mount(Inicio, { props: { ...base, ...props } });
}

describe("Inicio del panel del proveedor", () => {
    it("enseña la fecha que manda el servidor", () => {
        expect(montar().text()).toContain("Lunes, 24 de agosto de 2026");
    });

    it("pinta cada tarjeta de atención con su cifra, su etiqueta y su enlace", () => {
        const enlaces = montar().findAll("a");

        expect(montar().text()).toContain("Clientes en riesgo");
        expect(enlaces.some((enlace) => enlace.attributes("href") === "/proveedor/empresas")).toBe(true);
    });

    it("pinta cada tarjeta de negocio con su variación cuando la hay", () => {
        const texto = montar().text();

        expect(texto).toContain("Empresas activas");
        expect(texto).toContain("2 este mes");
    });

    it("no rompe cuando una tarjeta de negocio no tiene con qué comparar", () => {
        expect(montar().text()).toContain("todavía no hay con qué comparar");
    });
});
