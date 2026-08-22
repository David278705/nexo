import { readFileSync } from "node:fs";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick, reactive } from "vue";

const router = { get: vi.fn(), visit: vi.fn() };

vi.mock("@inertiajs/vue3", () => ({ router }));

const { ESPERA_DE_BUSQUEDA, TODOS, useListado } = await import("./useListado");

/*
 * El armazón se prueba solo, sin pantalla: si estuviera escrito dentro de una,
 * estas pruebas no podrían existir.
 *
 * Los filtros se declaran reactivos a propósito, que es como llegan de Inertia,
 * para comprobar que el composable los lee en cada llamada y no se queda con la
 * copia del primer render.
 */
function listado(cambios = {}) {
    const filtros = reactive({
        buscar: "",
        estado: "activo",
        cargo: "",
        orden: "nombre",
        direccion: "asc",
        porPagina: 25,
        ...(cambios.filtros ?? {}),
    });

    const pagina = reactive({ total: cambios.total ?? 43 });

    return {
        filtros,
        ...useListado({
            ruta: "/empleados",
            filtros: () => filtros,
            pagina: cambios.sinExportacion === true ? null : () => pagina,
            tieneFiltros: () => cambios.tieneFiltros ?? true,
            deLista: ["estado"],
        }),
    };
}

describe("useListado", () => {
    beforeEach(() => {
        vi.useFakeTimers();
        router.get.mockClear();
        router.visit.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("pide la página al servidor con los filtros puestos y los cambios encima", () => {
        listado().pedir({ page: 3 });

        expect(router.get).toHaveBeenCalledWith(
            "/empleados",
            expect.objectContaining({ estado: "activo", page: 3 }),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it("lee los filtros en cada llamada, no los del primer render", () => {
        const { filtros, pedir } = listado();

        filtros.estado = "inactivo";
        pedir({ page: 1 });

        expect(router.get.mock.calls[0][1]).toMatchObject({ estado: "inactivo" });
    });

    it("al pulsar otra columna empieza en ascendente", () => {
        listado().ordenar("cargo");

        expect(router.get.mock.calls[0][1]).toMatchObject({
            orden: "cargo",
            direccion: "asc",
            page: 1,
        });
    });

    it("al pulsar la columna ya ordenada invierte el sentido", () => {
        listado().ordenar("nombre");

        expect(router.get.mock.calls[0][1]).toMatchObject({
            orden: "nombre",
            direccion: "desc",
        });
    });

    it("al invertir vuelve a la primera página, para no quedar en una que ya no existe", () => {
        listado({ filtros: { direccion: "desc" } }).ordenar("nombre");

        expect(router.get.mock.calls[0][1]).toMatchObject({
            direccion: "asc",
            page: 1,
        });
    });

    it("quitar un filtro de lista lo devuelve a «todos», no a vacío", () => {
        listado().quitar("estado");

        expect(router.get.mock.calls[0][1]).toMatchObject({ estado: TODOS });
    });

    it("quitar un filtro de texto lo devuelve a vacío", () => {
        listado().quitar("cargo");

        expect(router.get.mock.calls[0][1]).toMatchObject({ cargo: "" });
    });

    it("limpiar va a la ruta sin ningún filtro", () => {
        listado().limpiar();

        expect(router.get).toHaveBeenCalledWith(
            "/empleados",
            {},
            { preserveScroll: true, replace: true },
        );
    });

    it("la fila completa lleva a la ficha", () => {
        listado().abrir({ id: 7 });

        expect(router.visit).toHaveBeenCalledWith("/empleados/7");
    });

    it("la búsqueda espera a que se termine de escribir antes de pedir", () => {
        const { buscar } = listado();

        buscar("res");
        buscar("restrepo");

        expect(router.get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(ESPERA_DE_BUSQUEDA);

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get.mock.calls[0][1]).toMatchObject({
            buscar: "restrepo",
            page: 1,
        });
    });

    it("la caja de búsqueda se vacía cuando el servidor devuelve el filtro quitado", async () => {
        const { filtros, busqueda } = listado({ filtros: { buscar: "restrepo" } });

        expect(busqueda.value).toBe("restrepo");

        filtros.buscar = "";
        await nextTick();

        expect(busqueda.value).toBe("");
    });

    it("dice cuántos registros exporta y que son los filtrados", () => {
        expect(listado().textoExportar.value).toBe(
            "Exportar 43 registros filtrados",
        );
    });

    it("con un solo registro lo dice en singular", () => {
        expect(listado({ total: 1 }).textoExportar.value).toBe(
            "Exportar 1 registro filtrado",
        );
    });

    it("sin filtros puestos no dice «filtrados», porque se lleva todo", () => {
        expect(listado({ tieneFiltros: false }).textoExportar.value).toBe(
            "Exportar 43 registros",
        );
    });

    it("la exportación lleva los filtros de la pantalla y no el tamaño de página", () => {
        const url = listado().urlExportar.value;

        expect(url).toContain("/empleados/exportar?");
        expect(url).toContain("estado=activo");
        expect(url).not.toContain("porPagina");
        // Un filtro vacío no viaja: ensuciaría la dirección sin filtrar nada.
        expect(url).not.toContain("cargo=");
    });

    it("una pantalla que no declara su página no tiene exportación", () => {
        const { urlExportar, textoExportar } = listado({ sinExportacion: true });

        expect(urlExportar.value).toBe("");
        expect(textoExportar.value).toBe("");
    });
});

/*
 * El gemelo de la prueba que en el servidor vigila `App\Listados\Listado`. No
 * mira comportamiento, mira que el armazón siga siendo uno: la razón de existir
 * de esta tarea es que la cuarta pantalla de listado no vuelva a copiarlo.
 */
describe("Las pantallas de listado no reescriben el armazón", () => {
    const PANTALLAS = [
        "resources/js/Pages/Empleados/Index.vue",
        "resources/js/Pages/Contratos/Index.vue",
        "resources/js/Pages/Proveedor/Empresas.vue",
        "resources/js/Pages/Incapacidades/Index.vue",
        "resources/js/Pages/Movimientos/Index.vue",
    ];

    const COPIADO = [
        "function pedir(",
        "function ordenar(",
        "function quitar(",
        "function limpiar(",
        "function abrir(",
        "function buscar(",
        "URLSearchParams",
        "setTimeout",
    ];

    it.each(PANTALLAS)("%s usa el composable y no su propia copia", (ruta) => {
        const codigo = readFileSync(ruta, "utf8");

        expect(codigo).toContain("useListado");

        COPIADO.forEach((copiado) => {
            expect(codigo).not.toContain(copiado);
        });
    });
});
