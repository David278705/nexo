import { describe, expect, it, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/alertas",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            {
                clave: "alertas",
                etiqueta: "Alertas enviadas",
                grupo: "Sistema",
                url: "/proveedor/alertas",
            },
        ],
    },
};

const get = vi.fn();
const post = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: {
        get: (...argumentos) => get(...argumentos),
        post: (...argumentos) => post(...argumentos),
        visit: vi.fn(),
    },
}));

const Alertas = (await import("./Alertas.vue")).default;

const filaEnviada = {
    id: 12,
    empresaId: 3,
    empresa: "Distribuidora El Progreso",
    programada: "17/08/2026",
    enviada: "17/08/2026, 6:02 a. m.",
    tipo: "Plazo para definir la renovación",
    empleado: "Juan Pablo Mejía",
    destinatarios: ["angela@elprogreso.co"],
    estado: "Enviada",
    tono: "exito",
    intentos: 1,
    asunto: "Plazo para definir la renovación de Juan Pablo",
    parrafos: ["El plazo para avisar la no renovación vence en dos días (19 de agosto)."],
    motivo: "",
    puedeReenviarse: true,
};

const filaFallida = {
    ...filaEnviada,
    id: 13,
    empresa: "Textiles Aurora",
    estado: "Fallida",
    tono: "error",
    intentos: 3,
    motivo: "El proveedor rechazó la dirección",
    puedeReenviarse: true,
};

const filaPendiente = {
    ...filaEnviada,
    id: 14,
    estado: "Pendiente de envío",
    tono: "neutro",
    puedeReenviarse: false,
};

const base = {
    alertas: {
        filas: [filaEnviada, filaFallida],
        pagina: 1,
        paginas: 1,
        total: 2,
        desde: 1,
        hasta: 2,
    },
    filtros: {
        desde: "",
        hasta: "",
        empresa: "todos",
        tipo: "todos",
        estado: "todos",
        orden: "programada",
        direccion: "desc",
        porPagina: 25,
    },
    aplicados: [],
    tieneFiltros: false,
    entregabilidad: { enviadas: 18, fallidas: 2, porcentaje: "90,0%" },
    opciones: {
        empresas: [
            { valor: "3", texto: "Distribuidora El Progreso" },
            { valor: "4", texto: "Textiles Aurora" },
        ],
        tipos: [{ valor: "plazo_de_renovacion", texto: "Plazo para definir la renovación" }],
        estados: [
            { valor: "enviada", texto: "Enviada" },
            { valor: "fallida", texto: "Fallida" },
        ],
        tamanos: [25, 50, 100],
    },
};

function montar(cambios = {}) {
    return mount(Alertas, { props: { ...base, ...cambios }, attachTo: document.body });
}

/**
 * El modal vive en un `Teleport` al `body`, así que sus botones no están
 * dentro del componente montado: se buscan en el documento, como en
 * `Suscripciones.test.js`.
 */
function botonDelModal(texto) {
    return [...document.body.querySelectorAll('[role="dialog"] button')].find(
        (uno) => uno.textContent.includes(texto),
    );
}

function pulsar(elemento) {
    elemento.dispatchEvent(new MouseEvent("click", { bubbles: true }));
}

beforeEach(() => {
    get.mockClear();
    post.mockClear();
    document.body.innerHTML = "";
});

describe("la consola de alertas del proveedor", () => {
    it("enseña las cifras de entregabilidad del mes", () => {
        const pantalla = montar();

        expect(pantalla.text()).toContain("18");
        expect(pantalla.text()).toContain("90,0%");
    });

    it("dice que la entregabilidad todavía no mide rebotes", () => {
        expect(montar().text()).toContain("proveedor de correo transaccional");
    });

    it("enseña la empresa de cada fila, no solo la del cliente", () => {
        const pantalla = montar();

        expect(pantalla.text()).toContain("Distribuidora El Progreso");
        expect(pantalla.text()).toContain("Textiles Aurora");
    });

    it("pide la página filtrada por empresa al cambiar el selector", async () => {
        const pantalla = montar();

        await pantalla.findAll("select")[0].setValue("3");

        expect(get).toHaveBeenCalledWith(
            "/proveedor/alertas",
            expect.objectContaining({ empresa: "3", page: 1 }),
            expect.any(Object),
        );
    });

    it("abre el detalle con el mismo texto que salió por correo", async () => {
        const pantalla = montar();

        await pantalla.find("tbody tr").trigger("click");

        expect(document.body.textContent).toContain(
            "El plazo para avisar la no renovación vence en dos días (19 de agosto).",
        );
    });

    it("muestra el motivo de la que no se pudo entregar", async () => {
        const pantalla = montar();

        await pantalla.findAll("tbody tr")[1].trigger("click");

        expect(document.body.textContent).toContain("El proveedor rechazó la dirección");
    });

    it("ofrece reenviar una alerta fallida", async () => {
        const pantalla = montar();

        await pantalla.findAll("tbody tr")[1].trigger("click");

        pulsar(botonDelModal("Reenviar"));

        expect(post).toHaveBeenCalledWith(
            "/proveedor/alertas/3/13/reenviar",
            {},
            expect.any(Object),
        );
    });

    it("no ofrece reenviar una que ya está pendiente de envío", async () => {
        const pantalla = montar({
            alertas: { ...base.alertas, filas: [filaPendiente] },
        });

        await pantalla.find("tbody tr").trigger("click");

        expect(botonDelModal("Reenviar")).toBeUndefined();
    });
});
