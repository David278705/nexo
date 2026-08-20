import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/contratos",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "contratos",
                    etiqueta: "Contratos",
                    grupo: "Personal",
                    url: "/contratos",
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
    vence: "90",
    contrato: "todos",
    prorrogas: "todos",
    orden: "vence",
    direccion: "asc",
    porPagina: 25,
};

const opciones = {
    vencimientos: [
        { valor: "30", texto: "Vencen en 30 días" },
        { valor: "60", texto: "Vencen en 60 días" },
        { valor: "90", texto: "Vencen en 90 días" },
        { valor: "vencidos", texto: "Ya vencidos" },
        { valor: "todos", texto: "Todos los contratos" },
    ],
    contratos: [
        { valor: "indefinido", texto: "Término indefinido" },
        { valor: "fijo", texto: "Término fijo" },
        { valor: "obra_labor", texto: "Obra o labor" },
    ],
    prorrogas: [
        { valor: "0", texto: "Sin prórrogas" },
        { valor: "1", texto: "1 o más" },
        { valor: "3", texto: "3 o más" },
        { valor: "todos", texto: "Cualquier número de prórrogas" },
    ],
    tamanos: [25, 50, 100],
};

const fila = {
    id: 7,
    empleado: "Juan Pablo Mejía Ochoa",
    documento: "1017234891",
    cargo: "Auxiliar de Bodega",
    contrato: "Término fijo",
    inicio: "15/04/2026",
    vence: "12/09/2026",
    prorrogas: 3,
    preaviso: "13/08/2026",
    estado: "Vence en 24 días",
    tono: "advertencia",
};

function montar(cambios = {}) {
    return mount(Index, {
        props: {
            contratos: {
                filas: [fila],
                pagina: 1,
                paginas: 1,
                total: 1,
                desde: 1,
                hasta: 1,
            },
            filtros,
            aplicados: [
                { clave: "vence", texto: "Vencimiento: Vencen en 90 días" },
            ],
            tieneFiltros: true,
            opciones,
            conteos: { vencidos: 0, porVencer: 1, dias: 90 },
            ...cambios,
        },
    });
}

beforeEach(() => {
    router.get.mockClear();
    router.visit.mockClear();
});

describe("el listado de contratos", function () {
    it("dice en la cabecera cuál es la vista por defecto", function () {
        expect(montar().text()).toContain(
            "los que vencen en los próximos 90 días",
        );
    });

    it("enseña las siete columnas del documento 3", function () {
        const encabezados = montar()
            .findAll("th")
            .map((th) => th.text().replace(/[↕↑↓]\s*$/, "").trim());

        expect(encabezados).toEqual([
            "Empleado",
            "Tipo",
            "Inicio",
            "Vence",
            "Prórrogas",
            "Límite de preaviso",
            "Estado",
        ]);
    });

    it("enseña la fecha límite de preaviso en la fila", function () {
        expect(montar().text()).toContain("13/08/2026");
    });

    it("lleva al detalle al pulsar la fila", async function () {
        await montar().find("tbody tr").trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/contratos/7");
    });

    it("pide la página al servidor al cambiar un filtro", async function () {
        const pantalla = montar();

        await pantalla.findAll("select")[0].setValue("30");

        expect(router.get).toHaveBeenCalledWith(
            "/contratos",
            expect.objectContaining({ vence: "30", page: 1 }),
            expect.anything(),
        );
    });

    it("dice cuántos registros se lleva la exportación y que son los filtrados", function () {
        expect(montar().text()).toContain("Exportar 1 registro filtrado");
    });

    it("no ofrece la línea de marcador en los filtros de lista cerrada", function () {
        // El primer selector es el de vencimiento: sus cinco opciones son
        // exactamente las cinco del servidor, sin un «Seleccione…» que no
        // significa nada aquí.
        expect(montar().findAll("select")[0].findAll("option")).toHaveLength(5);
    });

    it("avisa de los contratos que vencieron sin gestionar", function () {
        const pantalla = montar({ conteos: { vencidos: 2, porVencer: 5, dias: 90 } });

        expect(pantalla.text())
            .toContain("2 contratos vencieron sin registrar prórroga ni terminación.");
        expect(pantalla.text()).toContain("Ley 2466 de 2025");
    });

    it("no enseña el aviso cuando no hay ninguno vencido", function () {
        expect(montar().text()).not.toContain("sin registrar prórroga");
    });

    it("explica el vacío recordando que la vista por defecto filtra", function () {
        const pantalla = montar({
            contratos: {
                filas: [],
                pagina: 1,
                paginas: 1,
                total: 0,
                desde: null,
                hasta: null,
            },
        });

        expect(pantalla.text()).toContain(
            "Ningún contrato coincide con estos filtros.",
        );
        expect(pantalla.text()).toContain("Quita los filtros para verlos todos.");
    });
});
