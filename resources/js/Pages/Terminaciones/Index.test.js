import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const reload = vi.fn();

/*
 * Lo propio de esta pantalla es la lista dependiente del documento 1, 7.3 —los
 * motivos cambian con el tipo de salida— y, desde REV-03, que la misma lista
 * tenga que dejar puesto el motivo guardado cuando lo que se abre es una
 * corrección. El resto —filtros, listado, exportar— ya lo prueba
 * `useListado.test.js`, el flujo del documento `ModalDeNovedad.test.js` y el
 * mecanismo de las dos acciones `useNovedad.test.js`.
 */
const TIPOS = [
    { valor: "renuncia", texto: "Renuncia" },
    { valor: "mutuo_acuerdo", texto: "Terminación de contrato por mutuo acuerdo" },
    { valor: "expiracion_del_plazo", texto: "Expiración del plazo fijo pactado" },
];

const MOTIVOS_POR_TIPO = {
    renuncia: {
        motivos: ["Estudios", "Motivos de salud"],
        automatico: null,
    },
    mutuo_acuerdo: {
        motivos: ["Acuerdo económico de retiro", "Otro"],
        automatico: null,
    },
    expiracion_del_plazo: {
        motivos: ["Vencimiento del plazo pactado"],
        automatico: "Vencimiento del plazo pactado",
    },
};

const empleadoDeNovedad = {
    documento: "1015887432",
    encontrado: true,
    error: null,
    vinculacion: 4,
    nombre: "Carlos Ramírez",
    datos: [
        {
            etiqueta: "Empleado",
            valor: "Carlos Ramírez",
            pendiente: false,
            nota: "",
        },
    ],
};

const pagina = {
    url: "/terminaciones",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "terminaciones",
                    etiqueta: "Terminaciones",
                    grupo: "Movimientos",
                    url: "/terminaciones",
                },
            ],
        },
        flujoDeNovedad: {
            etiqueta: "Número de documento del empleado",
            ayuda: "El empleado debe estar registrado y activo.",
            tipos: [
                {
                    clave: "terminacion",
                    titulo: "Registrar terminación",
                    tituloEdicion: "Editar terminación",
                    modulo: "terminaciones",
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

function fila(cambios = {}) {
    return {
        id: 18,
        documento: "1015887432",
        nombre: "Carlos Ramírez",
        cargo: "Auxiliar de Ventas",
        salida: "31/07/2026",
        tipo: "Renuncia",
        motivo: "Motivos de salud",
        rotacion: "Voluntaria",
        tono: "neutro",
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
    id: 18,
    datos: empleadoDeNovedad.datos,
    valores: {
        vinculacion_id: 4,
        tipo_salida: "renuncia",
        motivo: "Motivos de salud",
        fecha_salida: "2026-07-31",
        observaciones: "",
    },
    contexto: {},
    confirmacion:
        "Vas a eliminar la terminación del 31 de julio de 2026 de Carlos " +
        "Ramírez. Carlos Ramírez volverá a estar activo y a recibir alertas. " +
        "Esta acción queda registrada en la auditoría.",
};

function montar(props = {}) {
    return mount(Index, {
        props: {
            terminaciones: listadoCon([]),
            filtros: {
                desde: "",
                hasta: "",
                tipo: "todos",
                motivo: "todos",
                orden: "salida",
                direccion: "desc",
                porPagina: 25,
            },
            aplicados: [],
            tieneFiltros: false,
            opciones: {
                tipos: TIPOS,
                motivosPorTipo: MOTIVOS_POR_TIPO,
                motivos: ["Estudios", "Motivos de salud"],
                tamanos: [25, 50, 100],
            },
            avisoDeTerminacion: null,
            novedad: null,
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
 * Se busca dentro del modal cuando hay uno abierto, y no en todo el documento:
 * la barra de filtros tiene sus propios campos «Tipo de salida» y «Motivo de
 * la baja», con la misma etiqueta que los del modal, y sin acotar se encuentra
 * siempre el primero en el orden del documento —el del filtro, no el del
 * formulario— dando un valor equivocado sin que la prueba lo note.
 */
function campo(etiqueta) {
    const raiz = document.body.querySelector('[role="dialog"]') ?? document.body;
    const label = [...raiz.querySelectorAll("label")].find((candidato) =>
        candidato.textContent.trim().startsWith(etiqueta),
    );

    return document.getElementById(label.getAttribute("for"));
}

function opcionesDe(etiqueta) {
    return [...campo(etiqueta).options].map((opcion) =>
        opcion.textContent.trim(),
    );
}

async function elegir(etiqueta, valor) {
    const select = campo(etiqueta);
    select.value = valor;
    select.dispatchEvent(new Event("change"));
    await nextTick();
}

/**
 * Abre el modal grande dejando resuelto al empleado de prueba.
 */
async function abrirYSeleccionar() {
    boton("Registrar terminación").dispatchEvent(new Event("click"));
    await nextTick();

    const documento = document.body.querySelector('[role="dialog"] input');
    documento.value = "1015887432";
    documento.dispatchEvent(new Event("input"));
    await nextTick();
    documento.dispatchEvent(new Event("blur"));
    await nextTick();

    boton("Continuar").dispatchEvent(new Event("click"));
    await nextTick();
}

describe("la lista dependiente de motivos (documento 1, 7.3)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("cambia las opciones del motivo con el tipo de salida", async () => {
        const componente = montar();

        await abrirYSeleccionar();
        await elegir("Tipo de salida", "renuncia");

        expect(opcionesDe("Motivo de la baja")).toEqual([
            "Seleccione…",
            "Estudios",
            "Motivos de salud",
        ]);

        await elegir("Tipo de salida", "mutuo_acuerdo");

        expect(opcionesDe("Motivo de la baja")).toEqual([
            "Seleccione…",
            "Acuerdo económico de retiro",
            "Otro",
        ]);

        componente.unmount();
    });

    it("olvida el motivo elegido cuando deja de pertenecer al tipo", async () => {
        const componente = montar();

        await abrirYSeleccionar();
        await elegir("Tipo de salida", "renuncia");
        await elegir("Motivo de la baja", "Estudios");
        await elegir("Tipo de salida", "mutuo_acuerdo");

        expect(campo("Motivo de la baja").value).toBe("");

        componente.unmount();
    });

    it("completa solo el motivo de los tipos que no lo preguntan", async () => {
        const componente = montar();

        await abrirYSeleccionar();
        await elegir("Tipo de salida", "expiracion_del_plazo");

        expect(campo("Motivo de la baja").value).toBe(
            "Vencimiento del plazo pactado",
        );
        expect(campo("Motivo de la baja").disabled).toBe(true);

        componente.unmount();
    });
});

/*
 * REV-03. Aquí no hay guardia de «el más reciente» —un empleado tiene una sola
 * salida registrada—, así que lo que se defiende es que la corrección abra con
 * lo guardado puesto, incluido el motivo, que es el campo que la lista
 * dependiente podría borrar por el camino.
 */
describe("corregir y eliminar una terminación (REV-03)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("ofrece las dos acciones en todas las filas", () => {
        const componente = montar({ terminaciones: listadoCon([fila()]) });

        expect(boton("Editar")).toBeDefined();
        expect(boton("Eliminar")).toBeDefined();

        componente.unmount();
    });

    it("pide el detalle de la fila al pulsar Editar, sin traerlo en el listado", async () => {
        const componente = montar({ terminaciones: listadoCon([fila()]) });

        boton("Editar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ["novedad"],
                data: { novedad: 18 },
            }),
        );

        componente.unmount();
    });

    it("abre la corrección con el tipo y el motivo guardados, sin borrar el motivo", async () => {
        const componente = montar({
            terminaciones: listadoCon([fila()]),
            novedad: DETALLE,
        });

        boton("Editar").dispatchEvent(new Event("click"));
        await nextTick();
        // El vigilante del tipo de salida corre después de que el formulario se
        // rellena: hay que dejarlo pasar para comprobar que no borró nada.
        await nextTick();

        expect(document.body.textContent).toContain("Editar terminación");
        expect(campo("Tipo de salida").value).toBe("renuncia");
        expect(campo("Motivo de la baja").value).toBe("Motivos de salud");
        expect(campo("Fecha de salida").value).toBe("2026-07-31");

        componente.unmount();
    });

    it("no vuelve a pedir el aviso de la consecuencia al corregir", async () => {
        const componente = montar({
            terminaciones: listadoCon([fila()]),
            novedad: DETALLE,
        });

        boton("Editar").dispatchEvent(new Event("click"));
        await nextTick();
        await nextTick();

        expect(reload).not.toHaveBeenCalledWith(
            expect.objectContaining({ only: ["avisoDeTerminacion"] }),
        );

        componente.unmount();
    });

    it("enseña antes de eliminar la consecuencia que escribió el servidor", async () => {
        const componente = montar({
            terminaciones: listadoCon([fila()]),
            novedad: DETALLE,
        });

        boton("Eliminar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(document.body.textContent).toContain(
            "volverá a estar activo y a recibir alertas",
        );

        componente.unmount();
    });
});
