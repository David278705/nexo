import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const get = vi.fn();

/*
 * Las columnas de la tabla (HD-14): la EPS va al final de los datos, después
 * de «Prórroga», y «Días» ordena como «Inicio» y «Fin». El orden lo resuelve
 * el servidor; aquí solo se comprueba que el encabezado lo pida.
 */
const pagina = {
    url: "/incapacidades",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                { clave: "incapacidades", etiqueta: "Incapacidades", grupo: "Novedades", url: "/incapacidades" },
            ],
        },
        flujoDeNovedad: {
            etiqueta: "Número de documento del empleado",
            ayuda: "El empleado debe estar registrado y activo.",
            tipos: [
                { clave: "incapacidad", titulo: "Registrar incapacidad", modulo: "incapacidades" },
            ],
        },
        empleadoDeNovedad: null,
        estado: "",
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { reload: vi.fn(), get, visit: vi.fn() },
}));

const Index = (await import("./Index.vue")).default;

const FILA = {
    id: 5,
    documento: "43887201",
    nombre: "Carolina Ramírez",
    cargo: "Analista de compras",
    inicio: "14/07/2026",
    fin: "16/07/2026",
    dias: 3,
    diagnostico: "J06.9",
    origen: "Común",
    prorroga: "No",
    eps: "Sura EPS",
};

function montar(filtros = {}) {
    return mount(Index, {
        props: {
            incapacidades: { filas: [FILA], pagina: 1, paginas: 1, total: 1, desde: 1, hasta: 1 },
            filtros: {
                buscar: "",
                desde: "",
                hasta: "",
                origen: "todos",
                eps: "todos",
                orden: "inicio",
                direccion: "asc",
                porPagina: 25,
                ...filtros,
            },
            aplicados: [],
            tieneFiltros: false,
            opciones: {
                origenes: [
                    { valor: "comun", texto: "Común" },
                    { valor: "laboral", texto: "Laboral" },
                ],
                eps: [{ valor: "1", texto: "Sura EPS" }],
                tamanos: [25, 50, 100],
            },
            diasDeIncapacidad: null,
            novedad: null,
        },
        attachTo: document.body,
    });
}

function encabezados() {
    return [...document.body.querySelectorAll("thead th")].map((th) =>
        th.textContent.trim(),
    );
}

describe("las columnas de incapacidades (HD-14)", () => {
    beforeEach(() => {
        get.mockClear();
        document.body.innerHTML = "";
    });

    it("enseña la EPS después de «Prórroga» y antes de las acciones", () => {
        const componente = montar();
        const titulos = encabezados();

        expect(titulos.indexOf("EPS")).toBe(titulos.indexOf("Prórroga") + 1);
        expect(titulos.indexOf("Acciones")).toBe(titulos.indexOf("EPS") + 1);
        expect(document.body.querySelector("tbody").textContent).toContain("Sura EPS");

        componente.unmount();
    });

    it("pide ordenar por días al pulsar el encabezado «Días»", () => {
        const componente = montar();

        const boton = [...document.body.querySelectorAll("thead button")].find(
            (candidato) => candidato.textContent.trim().startsWith("Días"),
        );
        boton.dispatchEvent(new Event("click"));

        expect(get).toHaveBeenCalledWith(
            "/incapacidades",
            expect.objectContaining({ orden: "dias", direccion: "asc", page: 1 }),
            expect.anything(),
        );

        componente.unmount();
    });

    it("al pulsar «Días» estando ya ordenado por días ascendente, invierte el sentido", () => {
        const componente = montar({ orden: "dias", direccion: "asc" });

        const boton = [...document.body.querySelectorAll("thead button")].find(
            (candidato) => candidato.textContent.trim().startsWith("Días"),
        );
        boton.dispatchEvent(new Event("click"));

        expect(get).toHaveBeenCalledWith(
            "/incapacidades",
            expect.objectContaining({ orden: "dias", direccion: "desc" }),
            expect.anything(),
        );

        componente.unmount();
    });
});
