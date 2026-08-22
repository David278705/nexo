import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const reload = vi.fn();

/*
 * El selector de tres tipos (F2-07) es la única lógica propia de esta
 * pantalla: el filtro que le antepone «Todos los tipos», el campo del modal
 * que obliga a elegir uno antes de poder guardar, y la petición de los días
 * corridos al servidor. El resto —filtros, listado, exportar— ya lo prueba
 * `useListado.test.js`, y el flujo del documento `ModalDeNovedad.test.js`.
 */
const TIPOS = [
    { valor: "licencia_no_remunerada", texto: "Licencia no remunerada" },
    { valor: "ausencia", texto: "Ausencia" },
    { valor: "suspension", texto: "Suspensión" },
];

const empleadoDeNovedad = {
    documento: "43887201",
    encontrado: true,
    error: null,
    vinculacion: 7,
    nombre: "Carolina Ramírez Gómez",
    datos: [
        {
            etiqueta: "Empleado",
            valor: "Carolina Ramírez Gómez",
            pendiente: false,
            nota: "",
        },
    ],
};

const pagina = {
    url: "/licencias",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "licencias",
                    etiqueta: "Licencias y ausencias",
                    grupo: "Novedades",
                    url: "/licencias",
                },
            ],
        },
        flujoDeNovedad: {
            etiqueta: "Número de documento del empleado",
            ayuda: "El empleado debe estar registrado y activo.",
            tipos: [
                {
                    clave: "licencia",
                    titulo: "Nueva licencia o ausencia",
                    modulo: "licencias",
                },
            ],
        },
        empleadoDeNovedad,
        estado: "",
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { reload, get: vi.fn(), visit: vi.fn() },
}));

const Index = (await import("./Index.vue")).default;

function montar(props = {}) {
    return mount(Index, {
        props: {
            licencias: {
                filas: [],
                pagina: 1,
                paginas: 1,
                total: 0,
                desde: null,
                hasta: null,
            },
            filtros: {
                desde: "",
                hasta: "",
                tipo: "todos",
                orden: "inicio",
                direccion: "desc",
                porPagina: 25,
            },
            aplicados: [],
            tieneFiltros: false,
            opciones: { tipos: TIPOS, tamanos: [25, 50, 100] },
            diasDeLicencia: null,
            ...props,
        },
        attachTo: document.body,
    });
}

function boton(texto) {
    return [...document.body.querySelectorAll("button")].find(
        (candidato) => candidato.textContent.trim() === texto,
    );
}

/**
 * El `<select>` que cuelga de una etiqueta, buscado por su texto igual que en
 * las demás pruebas de pantalla.
 */
function selector(etiqueta) {
    const label = [...document.body.querySelectorAll("label")].find(
        (candidato) => candidato.textContent.trim().startsWith(etiqueta),
    );

    return document.getElementById(label.getAttribute("for"));
}

function valorDelCampo(etiqueta) {
    return selector(etiqueta).value;
}

function opcionesDe(etiqueta) {
    return [...selector(etiqueta).options].map((opcion) =>
        opcion.textContent.trim(),
    );
}

/**
 * Abre el modal grande dejando resuelto al empleado de prueba, que es donde
 * aparecen el selector de tipo y las dos fechas.
 */
async function abrirYSeleccionar() {
    boton("Nueva licencia").dispatchEvent(new Event("click"));
    await nextTick();

    const campo = document.body.querySelector('[role="dialog"] input');
    campo.value = "43887201";
    campo.dispatchEvent(new Event("input"));
    await nextTick();
    campo.dispatchEvent(new Event("blur"));
    await nextTick();

    boton("Continuar").dispatchEvent(new Event("click"));
    await nextTick();
}

async function elegir(campo, valor) {
    const select = selector(campo);
    select.value = valor;
    select.dispatchEvent(new Event("change"));
    await nextTick();
}

describe("el selector de tres tipos (F2-07)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("ofrece los tres tipos del documento 1, 6.5 en el formulario", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        expect(opcionesDe("Tipo de novedad")).toEqual([
            "Seleccione…",
            "Licencia no remunerada",
            "Ausencia",
            "Suspensión",
        ]);

        componente.unmount();
    });

    it("antepone «Todos los tipos» al filtro del listado", () => {
        const componente = montar();

        expect(opcionesDe("Tipo")).toEqual([
            "Todos los tipos",
            "Licencia no remunerada",
            "Ausencia",
            "Suspensión",
        ]);

        componente.unmount();
    });

    it("pide los días corridos al servidor cuando están las dos fechas", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        const inicio = selector("Fecha inicial");
        inicio.value = "2026-07-02";
        inicio.dispatchEvent(new Event("input"));
        const fin = selector("Fecha final");
        fin.value = "2026-07-07";
        fin.dispatchEvent(new Event("input"));
        await nextTick();

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ["diasDeLicencia"],
                data: { fecha_inicio: "2026-07-02", fecha_fin: "2026-07-07" },
            }),
        );

        componente.unmount();
    });

    it("enseña los días que contó el servidor, sin restar fechas en el navegador", async () => {
        const componente = montar({ diasDeLicencia: "6 días" });

        await abrirYSeleccionar();

        expect(valorDelCampo("Días")).toBe("6 días");

        componente.unmount();
    });

    it("no deja guardar mientras no se haya elegido el tipo, aunque los días ya estén contados", async () => {
        const componente = montar({ diasDeLicencia: "6 días" });

        await abrirYSeleccionar();

        expect(boton("Guardar").disabled).toBe(true);

        await elegir("Tipo de novedad", "suspension");

        expect(boton("Guardar").disabled).toBe(false);

        componente.unmount();
    });
});
