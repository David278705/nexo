import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const reload = vi.fn();

/*
 * Lo propio de esta pantalla (F2-08) es que el formulario enseña la situación
 * actual del empleado y que el nuevo cargo depende del tipo de movimiento. El
 * resto —filtros, listado, exportar— ya lo prueba `useListado.test.js`, y el
 * flujo del documento `ModalDeNovedad.test.js`.
 */
const TIPOS = [
    { valor: "aumento_salarial", texto: "Aumento salarial" },
    { valor: "promocion", texto: "Promoción" },
];

const TIPOS_DEL_FORMULARIO = [
    { valor: "aumento_salarial", texto: "Aumento salarial — mismo cargo" },
    { valor: "promocion", texto: "Promoción — cambia de cargo" },
];

const empleadoDeNovedad = {
    documento: "43887201",
    encontrado: true,
    error: null,
    vinculacion: 7,
    nombre: "Carolina Restrepo Vélez",
    datos: [
        {
            etiqueta: "Empleado",
            valor: "Carolina Restrepo Vélez",
            pendiente: false,
            nota: "",
        },
    ],
};

const pagina = {
    url: "/movimientos",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "movimientos",
                    etiqueta: "Movimientos",
                    grupo: "Cambios y salidas",
                    url: "/movimientos",
                },
            ],
        },
        flujoDeNovedad: {
            etiqueta: "Número de documento del empleado",
            ayuda: "El empleado debe estar registrado y activo.",
            tipos: [
                {
                    clave: "movimiento",
                    titulo: "Nuevo movimiento salarial",
                    tituloEdicion: "Editar movimiento salarial",
                    modulo: "movimientos",
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
            movimientos: {
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
                cargo: "",
                orden: "efectivo",
                direccion: "desc",
                porPagina: 25,
            },
            aplicados: [],
            tieneFiltros: false,
            opciones: {
                tipos: TIPOS,
                tiposDelFormulario: TIPOS_DEL_FORMULARIO,
                cargos: ["Analista Senior", "Coordinadora de Operaciones"],
                tamanos: [25, 50, 100],
            },
            situacionActual: null,
            novedad: null,
            ...props,
        },
        attachTo: document.body,
    });
}

/**
 * Una fila del listado. `reversible` es lo que decide REV-03: solo el último
 * movimiento de cada empleado ofrece Editar y Eliminar.
 */
function fila(cambios = {}) {
    return {
        id: 31,
        documento: "43887201",
        nombre: "Carolina Restrepo",
        cargo: "Coordinadora de Operaciones",
        tipo: "Promoción",
        cargoAnterior: "Analista Senior",
        cargoNuevo: "Coordinadora de Operaciones",
        salarioAnterior: "$ 2.700.000",
        salarioNuevo: "$ 3.200.000",
        efectivo: "15/08/2026",
        reversible: true,
        ...cambios,
    };
}

function listadoCon(filas) {
    return {
        filas,
        pagina: 1,
        paginas: 1,
        total: filas.length,
        desde: 1,
        hasta: filas.length,
    };
}

/**
 * El detalle que contesta el servidor al pulsar Editar o Eliminar, tal como lo
 * arma `App\Novedades\DetalleDeNovedad`.
 */
const DETALLE = {
    id: 31,
    datos: [
        {
            etiqueta: "Empleado",
            valor: "Carolina Restrepo Vélez",
            pendiente: false,
            nota: "",
        },
    ],
    valores: {
        vinculacion_id: 7,
        tipo: "promocion",
        cargo_nuevo: "Coordinadora de Operaciones",
        salario_nuevo: "3200000.00",
        fecha_efectividad: "2026-08-15",
    },
    contexto: { cargo: "Analista Senior", salario: "$ 2.700.000" },
    confirmacion:
        "Vas a eliminar la promoción del 15 de agosto de 2026 de Carolina " +
        "Restrepo Vélez. El cargo de Carolina Restrepo Vélez volverá a Analista " +
        "Senior y su salario a $ 2.700.000. Esta acción queda registrada en la " +
        "auditoría.",
};

const SITUACION = { cargo: "Analista Senior", salario: "$ 3.200.000" };

function boton(texto) {
    return [...document.body.querySelectorAll("button")].find(
        (candidato) => candidato.textContent.trim() === texto,
    );
}

/**
 * El campo que cuelga de una etiqueta, buscado por su texto igual que en las
 * demás pruebas de pantalla.
 */
function campo(etiqueta) {
    const label = [...document.body.querySelectorAll("label")].find(
        (candidato) => candidato.textContent.trim().startsWith(etiqueta),
    );

    return document.getElementById(label.getAttribute("for"));
}

function opcionesDe(etiqueta) {
    return [...campo(etiqueta).options].map((opcion) =>
        opcion.textContent.trim(),
    );
}

/**
 * Abre el modal grande dejando resuelto al empleado de prueba, que es donde
 * aparecen el tipo de movimiento y los cuatro campos de cargo y salario.
 */
async function abrirYSeleccionar() {
    boton("Nuevo movimiento").dispatchEvent(new Event("click"));
    await nextTick();

    const documento = document.body.querySelector('[role="dialog"] input');
    documento.value = "43887201";
    documento.dispatchEvent(new Event("input"));
    await nextTick();
    documento.dispatchEvent(new Event("blur"));
    await nextTick();

    boton("Continuar").dispatchEvent(new Event("click"));
    await nextTick();
}

async function elegir(etiqueta, valor) {
    const select = campo(etiqueta);
    select.value = valor;
    select.dispatchEvent(new Event("change"));
    await nextTick();
}

async function escribir(etiqueta, valor) {
    const input = campo(etiqueta);
    input.value = valor;
    input.dispatchEvent(new Event("input"));
    await nextTick();
}

describe("aumentos y promociones (F2-08)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("antepone «Todos los movimientos» al filtro del listado", () => {
        const componente = montar();

        expect(opcionesDe("Tipo")).toEqual([
            "Todos los movimientos",
            "Aumento salarial",
            "Promoción",
        ]);

        componente.unmount();
    });

    it("ofrece los dos tipos con su consecuencia en el formulario", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        expect(opcionesDe("Tipo de movimiento")).toEqual([
            "Seleccione…",
            "Aumento salarial — mismo cargo",
            "Promoción — cambia de cargo",
        ]);

        componente.unmount();
    });

    it("pide al servidor el cargo y el salario vigentes al resolver al empleado", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ["situacionActual"],
                data: { vinculacion_id: 7 },
            }),
        );

        componente.unmount();
    });

    it("enseña la situación actual de solo lectura, sin calcularla en el navegador", async () => {
        const componente = montar({ situacionActual: SITUACION });

        await abrirYSeleccionar();

        expect(campo("Cargo actual").value).toBe("Analista Senior");
        expect(campo("Cargo actual").readOnly).toBe(true);
        expect(campo("Salario actual").value).toBe("$ 3.200.000");
        expect(campo("Salario actual").readOnly).toBe(true);

        componente.unmount();
    });

    it("repite el cargo actual y no lo deja editar en un aumento salarial", async () => {
        const componente = montar({ situacionActual: SITUACION });

        await abrirYSeleccionar();
        await elegir("Tipo de movimiento", "aumento_salarial");

        expect(campo("Nuevo cargo").value).toBe("Analista Senior");
        expect(campo("Nuevo cargo").readOnly).toBe(true);

        componente.unmount();
    });

    it("deja escribir el nuevo cargo cuando es una promoción", async () => {
        const componente = montar({ situacionActual: SITUACION });

        await abrirYSeleccionar();
        await elegir("Tipo de movimiento", "promocion");

        expect(campo("Nuevo cargo").readOnly).toBe(false);

        await escribir("Nuevo cargo", "Coordinadora de Operaciones");

        expect(campo("Nuevo cargo").value).toBe("Coordinadora de Operaciones");

        componente.unmount();
    });

    it("no deja guardar mientras falte el tipo, el salario o la fecha", async () => {
        const componente = montar({ situacionActual: SITUACION });

        await abrirYSeleccionar();

        expect(boton("Guardar").disabled).toBe(true);

        await elegir("Tipo de movimiento", "aumento_salarial");
        expect(boton("Guardar").disabled).toBe(true);

        await escribir("Nuevo salario", "3500000");
        expect(boton("Guardar").disabled).toBe(true);

        await escribir("Fecha de efectividad", "2026-09-01");
        expect(boton("Guardar").disabled).toBe(false);

        componente.unmount();
    });

    it("no deja guardar una promoción sin el nuevo cargo", async () => {
        const componente = montar({ situacionActual: SITUACION });

        await abrirYSeleccionar();
        await elegir("Tipo de movimiento", "promocion");
        await escribir("Nuevo salario", "3500000");
        await escribir("Fecha de efectividad", "2026-09-01");
        await escribir("Nuevo cargo", "");

        expect(boton("Guardar").disabled).toBe(true);

        await escribir("Nuevo cargo", "Coordinadora de Operaciones");

        expect(boton("Guardar").disabled).toBe(false);

        componente.unmount();
    });
});

/*
 * REV-03. Lo que se defiende aquí es lo que la pantalla decide sola: a qué
 * filas ofrece las dos acciones y qué enseña como punto de partida al corregir.
 * El efecto de las acciones lo prueba el servidor, y el mecanismo de abrirlas,
 * `useNovedad.test.js`.
 */
describe("corregir y eliminar un movimiento (REV-03)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("ofrece las dos acciones en el último movimiento del empleado", () => {
        const componente = montar({ movimientos: listadoCon([fila()]) });

        expect(boton("Editar")).toBeDefined();
        expect(boton("Eliminar")).toBeDefined();
        expect(document.body.textContent).not.toContain("Hay uno posterior");

        componente.unmount();
    });

    it("no las ofrece, y dice por qué, cuando hay otro movimiento encima", () => {
        const componente = montar({
            movimientos: listadoCon([fila({ reversible: false })]),
        });

        expect(boton("Editar")).toBeUndefined();
        expect(boton("Eliminar")).toBeUndefined();
        expect(document.body.textContent).toContain("Hay uno posterior");

        componente.unmount();
    });

    it("pide el detalle de la fila al pulsar Editar, sin traerlo en el listado", async () => {
        const componente = montar({ movimientos: listadoCon([fila()]) });

        boton("Editar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ only: ["novedad"], data: { novedad: 31 } }),
        );

        componente.unmount();
    });

    it("al corregir enseña el punto de partida del movimiento, no el del empleado", async () => {
        // `situacionActual` trae los valores vigentes del empleado, que ya son
        // el resultado de este movimiento: los que se enseñan tienen que ser
        // los que la fila guardó como anteriores.
        const componente = montar({
            movimientos: listadoCon([fila()]),
            novedad: DETALLE,
            situacionActual: SITUACION,
        });

        boton("Editar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(campo("Cargo actual").value).toBe("Analista Senior");
        expect(campo("Salario actual").value).toBe("$ 2.700.000");
        // Se enseña a la colombiana (PROD-11); lo que viaja al servidor sigue
        // siendo el número limpio.
        expect(campo("Nuevo salario").value).toBe("$ 3.200.000");
        expect(campo("Nuevo cargo").value).toBe("Coordinadora de Operaciones");

        componente.unmount();
    });

    it("abre el modal de corrección con su propio título y sin pedir el documento", async () => {
        const componente = montar({
            movimientos: listadoCon([fila()]),
            novedad: DETALLE,
        });

        boton("Editar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(document.body.textContent).toContain("Editar movimiento salarial");
        expect(boton("Continuar")).toBeUndefined();

        componente.unmount();
    });

    it("enseña antes de eliminar la consecuencia que escribió el servidor", async () => {
        const componente = montar({
            movimientos: listadoCon([fila()]),
            novedad: DETALLE,
        });

        boton("Eliminar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(document.body.textContent).toContain(
            "volverá a Analista Senior y su salario a $ 2.700.000",
        );

        componente.unmount();
    });
});
