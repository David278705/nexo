import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/empleados",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "empleados",
                    etiqueta: "Empleados",
                    grupo: "Personal",
                    url: "/empleados",
                },
            ],
        },
    },
};

const router = { get: vi.fn(), visit: vi.fn() };

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router,
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Index = (await import("./Index.vue")).default;

const filtros = {
    buscar: "",
    estado: "activo",
    contrato: "todos",
    cargo: "",
    ingresoDesde: "",
    ingresoHasta: "",
    orden: "nombre",
    direccion: "asc",
    porPagina: 25,
};

const opciones = {
    estados: [
        { valor: "activo", texto: "Activo" },
        { valor: "inactivo", texto: "Inactivo" },
    ],
    contratos: [
        { valor: "indefinido", texto: "Término indefinido" },
        { valor: "fijo", texto: "Término fijo" },
    ],
    cargos: ["Analista", "Conductor"],
    tamanos: [25, 50, 100],
};

function montar(cambios = {}) {
    return mount(Index, {
        props: {
            empleados: {
                filas: [
                    {
                        id: 7,
                        documento: "43887201",
                        nombre: "Carolina Restrepo Vélez",
                        cargo: "Coordinadora de Operaciones",
                        contrato: "Término indefinido",
                        ingreso: "03/02/2022",
                        estado: "Activo",
                        activo: 1,
                    },
                ],
                pagina: 1,
                paginas: 1,
                total: 1,
                desde: 1,
                hasta: 1,
            },
            filtros,
            aplicados: [{ clave: "estado", texto: "Estado: Activo" }],
            tieneFiltros: true,
            opciones,
            conteos: { activos: 34, inactivos: 9 },
            ...cambios,
        },
    });
}

describe("Listado de empleados", () => {
    beforeEach(() => {
        router.get.mockClear();
        router.visit.mockClear();
    });

    it("muestra la planta de la empresa junto al título", () => {
        expect(montar().text()).toContain("34 activos · 9 inactivos");
    });

    it("dice cuántos registros exporta y que son los filtrados", () => {
        expect(montar().text()).toContain("Exportar 1 registro filtrado");
    });

    it("lleva los filtros puestos en la dirección de la exportación", () => {
        const enlace = montar().find('a[download]');

        expect(enlace.attributes("href")).toContain("estado=activo");
        expect(enlace.attributes("href")).not.toContain("porPagina");
    });

    it("al pulsar la columna ya ordenada invierte el sentido", async () => {
        const componente = montar();

        await componente.findAll("thead th")[1].find("button").trigger("click");

        expect(router.get.mock.calls[0][1]).toMatchObject({
            orden: "nombre",
            direccion: "desc",
            page: 1,
        });
    });

    it("al pulsar otra columna empieza en ascendente", async () => {
        const componente = montar();

        await componente.findAll("thead th")[0].find("button").trigger("click");

        expect(router.get.mock.calls[0][1]).toMatchObject({
            orden: "documento",
            direccion: "asc",
        });
    });

    it("la fila completa lleva a la ficha del empleado", async () => {
        const componente = montar();

        await componente.find("tbody tr").trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/empleados/7");
    });

    it("sin empleados ofrece registrar el primero, con el texto del documento", () => {
        const componente = montar({
            empleados: { filas: [], pagina: 1, paginas: 1, total: 0, desde: null, hasta: null },
            aplicados: [],
            tieneFiltros: false,
        });

        expect(componente.text()).toContain("Aún no hay empleados registrados.");
        expect(componente.text()).toContain(
            "Aquí verás a todo tu personal y desde aquí podrás registrar contrataciones.",
        );
        expect(componente.text()).toContain("Registrar empleado");
    });

    it("sin resultados por los filtros ofrece quitarlos, no registrar", () => {
        const componente = montar({
            empleados: { filas: [], pagina: 1, paginas: 1, total: 0, desde: null, hasta: null },
        });

        expect(componente.text()).toContain(
            "No encontramos resultados con estos filtros.",
        );
        expect(componente.text()).toContain("Quitar filtros");
        expect(componente.text()).not.toContain("Registrar empleado");
    });

    it("quitar el filtro de estado lo devuelve a «todos», no a vacío", async () => {
        const componente = montar();

        await componente.find('button[aria-label="Quitar el filtro Estado: Activo"]').trigger("click");

        expect(router.get.mock.calls[0][1]).toMatchObject({ estado: "todos" });
    });
});
