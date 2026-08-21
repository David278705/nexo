import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/empresas",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            {
                clave: "empresas",
                etiqueta: "Empresas",
                grupo: "Clientes",
                url: "/proveedor/empresas",
            },
        ],
    },
};

const pedir = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: {
        get: (...argumentos) => pedir(...argumentos),
        visit: (...argumentos) => pedir(...argumentos),
    },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Empresas = (await import("./Empresas.vue")).default;

const fila = {
    id: 7,
    empresa: "Distribuidora El Progreso S.A.S.",
    nit: "900123456-7",
    plan: "Pequeña",
    empleados: "34 de 50",
    excedePlan: false,
    usuarios: "2 de 2",
    uso: "Activa",
    usoTono: "exito",
    ingreso: "17/08/2026",
    pago: "Sin cobros todavía",
    estado: "Activa",
    estadoTono: "exito",
};

const props = {
    empresas: {
        filas: [fila],
        total: 1,
        pagina: 1,
        paginas: 1,
        desde: 1,
        hasta: 1,
    },
    filtros: {
        buscar: "",
        estado: "todos",
        plan: "",
        salud: "todos",
        orden: "empresa",
        direccion: "asc",
        porPagina: 25,
    },
    aplicados: [],
    tieneFiltros: false,
    opciones: {
        estados: [{ valor: "activa", texto: "Activa" }],
        saludes: [{ valor: "sin_uso", texto: "Sin uso" }],
        planes: ["Pequeña"],
        tamanos: [25, 50, 100],
    },
    conteos: [
        { estado: "Activa", cuantas: 12, texto: "12 activas" },
        { estado: "Suspendida", cuantas: 1, texto: "1 suspendida" },
        { estado: "En prueba", cuantas: 2, texto: "2 en prueba" },
    ],
    pagoPendiente: "Sin cobros todavía",
    accion: { texto: "+ Crear empresa", url: "/proveedor/empresas/nueva" },
};

function montar(cambios = {}) {
    return mount(Empresas, { props: { ...props, ...cambios } });
}

describe("el listado de empresas del panel", () => {
    it("enseña la empresa con su NIT, su plan y sus indicadores", () => {
        const pintado = montar().text();

        expect(pintado).toContain("Distribuidora El Progreso S.A.S.");
        expect(pintado).toContain("NIT 900123456-7");
        expect(pintado).toContain("34 de 50");
        expect(pintado).toContain("Activa");
    });

    it("resume los estados como el prototipo", () => {
        expect(montar().text()).toContain("12 activas · 1 suspendida · 2 en prueba");
    });

    it("resalta los empleados solo cuando superan los del plan", () => {
        const dentro = montar();

        expect(dentro.html()).not.toContain("bg-advertencia-fondo");

        const fuera = montar({
            empresas: {
                ...props.empresas,
                filas: [{ ...fila, empleados: "61 de 50", excedePlan: true }],
            },
        });

        // Con palabras además del color, que es la regla de interfaz: el
        // resaltado solo no puede ser el portador de la información.
        expect(fuera.html()).toContain("bg-advertencia-fondo");
        expect(fuera.html()).toContain("Supera los empleados incluidos en el plan");
    });

    it("dice que el pago todavía no tiene de dónde salir", () => {
        expect(montar().text()).toContain("Sin cobros todavía");
    });

    it("ofrece crear una empresa", () => {
        expect(
            montar().find("a[href='/proveedor/empresas/nueva']").exists(),
        ).toBe(true);
    });

    it("pide la ficha al abrir una fila", async () => {
        pedir.mockClear();

        const pantalla = montar();

        await pantalla.find("tbody tr").trigger("click");

        expect(pedir).toHaveBeenCalledWith("/proveedor/empresas/7");
    });
});
