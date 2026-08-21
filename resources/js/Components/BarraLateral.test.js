import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

// `Link` necesita el router que instala `createInertiaApp`. Se reemplaza por un
// ancla, que es lo que termina pintando.
vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const BarraLateral = (await import("./BarraLateral.vue")).default;

const MODULOS = [
    { clave: "inicio", etiqueta: "Inicio", grupo: null, url: "/" },
    { clave: "empleados", etiqueta: "Empleados", grupo: "Personal", url: "/empleados" },
    { clave: "contratos", etiqueta: "Contratos", grupo: "Personal", url: "/contratos" },
    { clave: "vacaciones", etiqueta: "Vacaciones", grupo: "Novedades", url: "/vacaciones" },
];

function montar(props = {}) {
    return mount(BarraLateral, {
        props: { modulos: MODULOS, urlActual: "/", ...props },
    });
}

describe("BarraLateral", () => {
    it("pinta un enlace por módulo, en el orden en que llegan", () => {
        const enlaces = montar().findAll("a");

        expect(enlaces).toHaveLength(4);
        expect(enlaces.map((enlace) => enlace.attributes("href"))).toEqual([
            "/",
            "/empleados",
            "/contratos",
            "/vacaciones",
        ]);
    });

    it("agrupa los módulos bajo su encabezado y deja Inicio suelto", () => {
        const grupos = montar()
            .findAll("nav p")
            .map((titulo) => titulo.text());

        expect(grupos).toEqual(["Personal", "Novedades"]);
    });

    it("no pinta el módulo que no llega, porque el servidor ya lo filtró", () => {
        const componente = montar({
            modulos: MODULOS.filter((modulo) => modulo.clave !== "vacaciones"),
        });

        expect(componente.text()).not.toContain("Vacaciones");
    });

    it("marca como activa la sección de la dirección actual", () => {
        const componente = montar({ urlActual: "/empleados" });
        const activos = componente.findAll('[aria-current="page"]');

        expect(activos).toHaveLength(1);
        expect(activos[0].attributes("href")).toBe("/empleados");
    });

    it("mantiene activa la sección cuando la dirección es una ruta hija", () => {
        const activos = montar({ urlActual: "/empleados/7" }).findAll(
            '[aria-current="page"]',
        );

        expect(activos[0].attributes("href")).toBe("/empleados");
    });

    it("no da por activo Inicio estando en otra sección", () => {
        const activos = montar({ urlActual: "/contratos" }).findAll(
            '[aria-current="page"]',
        );

        expect(activos).toHaveLength(1);
        expect(activos[0].attributes("href")).toBe("/contratos");
    });

    it("resalta la sección más específica cuando la raíz no es «/»", () => {
        // Es el caso del panel del proveedor: su inicio es `/proveedor` y las
        // demás secciones son direcciones hijas suyas.
        const activos = montar({
            modulos: [
                { clave: "inicio", etiqueta: "Inicio", grupo: null, url: "/proveedor" },
                {
                    clave: "empresas",
                    etiqueta: "Empresas",
                    grupo: "Clientes",
                    url: "/proveedor/empresas",
                },
            ],
            urlActual: "/proveedor/empresas",
        }).findAll('[aria-current="page"]');

        expect(activos).toHaveLength(1);
        expect(activos[0].attributes("href")).toBe("/proveedor/empresas");
    });

    it("avisa al navegar, para que el menú desplegado se cierre", async () => {
        const componente = montar();

        await componente.findAll("a")[1].trigger("click");

        expect(componente.emitted("navegar")).toHaveLength(1);
    });
});
