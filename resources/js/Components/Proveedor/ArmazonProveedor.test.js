import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

/*
 * El armazón del panel es el del cliente con otras secciones y otro rótulo. Lo
 * que hay que comprobar es justo eso: que pinta las secciones del proveedor y
 * **no** los módulos del cliente, y que el rótulo dice de qué aplicación se
 * trata. Quien tiene las dos abiertas necesita saberlo de un vistazo.
 */
const pagina = {
    url: "/proveedor/empresas",
    props: {
        usuario: {
            nombre: "David Lozano",
            correo: "david@nexo.co",
            rol: "owner",
            empresa: null,
            nit: null,
            modulos: [],
        },
        secciones: [
            { clave: "inicio", etiqueta: "Inicio", grupo: null, url: "/proveedor" },
            {
                clave: "empresas",
                etiqueta: "Empresas",
                grupo: "Clientes",
                url: "/proveedor/empresas",
            },
            {
                clave: "catalogos",
                etiqueta: "Catálogos",
                grupo: "Sistema",
                url: "/proveedor/catalogos",
            },
        ],
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    usePage: () => pagina,
    router: { post: vi.fn() },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const ArmazonProveedor = (
    await import("./ArmazonProveedor.vue")
).default;

function montar() {
    return mount(ArmazonProveedor, {
        slots: { default: "<p>Contenido del panel</p>" },
        attachTo: document.body,
    });
}

describe("ArmazonProveedor", () => {
    it("envuelve el contenido de la pantalla", () => {
        expect(montar().text()).toContain("Contenido del panel");
    });

    it("dice en el menú y en la franja superior que es el panel del proveedor", () => {
        const texto = montar().text();

        expect(texto).toContain("Panel del proveedor");
        expect(texto).toContain("Administración y operación");
    });

    it("pinta las secciones del panel agrupadas", () => {
        const texto = montar().text();

        expect(texto).toContain("Clientes");
        expect(texto).toContain("Empresas");
        expect(texto).toContain("Catálogos");
    });

    it("resalta la sección de la dirección actual", () => {
        const activos = montar().findAll('[aria-current="page"]');

        expect(activos).toHaveLength(1);
        expect(activos[0].attributes("href")).toBe("/proveedor/empresas");
    });

    it("lleva el azul oscuro que lo distingue del sistema del cliente", () => {
        expect(montar().html()).toContain("bg-marca-oscuro");
    });
});
