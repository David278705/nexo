import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const enviarPost = vi.fn();
const enviarPut = vi.fn();
const post = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => {
    const { reactive } = await import("vue");

    return {
        ...(await importarOriginal()),
        Head: { name: "Head", template: "<span />" },
        Link: { name: "Link", template: "<a><slot /></a>" },
        usePage: () => ({
            url: "/proveedor/suscripciones",
            props: { secciones: [] },
        }),
        useForm: (datos) =>
            reactive({
                ...datos,
                errors: {},
                processing: false,
                post: (...argumentos) => enviarPost(...argumentos),
                put: (...argumentos) => enviarPut(...argumentos),
                reset: vi.fn(),
                clearErrors: vi.fn(),
            }),
        router: {
            post: (...argumentos) => post(...argumentos),
        },
    };
});

const Suscripciones = (await import("./Suscripciones.vue")).default;

const base = {
    ingresoRecurrente: "$ 4.180.000",
    aviso: "Textiles Aurora Ltda. lleva 22 días de mora. Según la escalera acordada, la suspensión es a los 30 días. Conviene llamar antes.",
    suscripciones: [
        {
            id: 7,
            empresaId: 3,
            empresa: "Textiles Aurora Ltda.",
            plan: "Mediana",
            valorMensual: "$ 720.000",
            proximoCobro: "26/07/2026",
            medioDePago: "Transferencia",
            cobro: "Mora de 22 días",
            cobroTono: "error",
            estado: "Activa",
            estadoTono: "exito",
        },
    ],
    planes: [
        {
            id: 2,
            nombre: "Mediana",
            precio: "$ 720.000",
            precioMensual: "720000.00",
            empleadosIncluidos: 200,
            usuariosIncluidos: 4,
            cupos: "Hasta 200 empleados · 4 usuarios",
            activo: true,
            estado: "Activo",
            tono: "exito",
            contratado: 2,
        },
    ],
    distribucion: [{ etiqueta: "Mediana", cuantos: 2 }],
    escalera: [
        { etiqueta: "Primer aviso", dias: 5, texto: "5 días" },
        { etiqueta: "Segundo aviso", dias: 15, texto: "15 días" },
        { etiqueta: "Suspensión", dias: 30, texto: "30 días" },
    ],
    empresasSinSuscripcion: [{ valor: "9", texto: "Panadería La Espiga S.A.S." }],
    mediosDePago: [{ valor: "transferencia", texto: "Transferencia" }],
    planesParaElegir: [{ valor: "2", texto: "Mediana · 200 empleados · 4 usuarios" }],
    sinPlanes: "Todavía no hay ningún plan.",
    avisoDeLaSuspension: "Suspender bloquea el acceso pero no elimina nada.",
};

function montar(cambios = {}) {
    return mount(Suscripciones, {
        props: { ...base, ...cambios },
        attachTo: document.body,
    });
}

function boton(pantalla, texto) {
    return pantalla.findAll("button").find((uno) => uno.text().includes(texto));
}

/**
 * Los modales viven en un `Teleport` al `body`, así que sus botones no están
 * dentro del componente montado: se buscan en el documento, igual que en
 * `Modal.test.js`.
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
    enviarPost.mockClear();
    enviarPut.mockClear();
    post.mockClear();
    document.body.innerHTML = "";
});

describe("la pantalla de suscripciones", () => {
    it("enseña el ingreso recurrente y el aviso de mora escritos por el servidor", () => {
        const pantalla = montar();

        expect(pantalla.text()).toContain("$ 4.180.000 de ingreso recurrente");
        expect(pantalla.text()).toContain("lleva 22 días de mora");
    });

    it("no enseña ningún aviso cuando nadie está en mora", () => {
        const pantalla = montar({ aviso: null, suscripciones: [] });

        expect(pantalla.text()).not.toContain("Conviene llamar antes");
    });

    it("enseña las tres tarjetas del prototipo", () => {
        const pantalla = montar();

        expect(pantalla.text()).toContain("Planes");
        expect(pantalla.text()).toContain("Distribución por plan");
        expect(pantalla.text()).toContain("Escalera de mora");
        expect(pantalla.text()).toContain("30 días");
    });

    it("dice que la tabla de precios está pendiente cuando no hay planes", () => {
        const pantalla = montar({ planes: [], distribucion: [] });

        expect(pantalla.text()).toContain("Todavía no hay ningún plan.");
    });

    it("crea un plan nuevo con los cuatro campos vacíos", async () => {
        const pantalla = montar();

        await boton(pantalla, "Agregar plan").trigger("click");
        pulsar(botonDelModal("Guardar plan"));

        expect(enviarPost).toHaveBeenCalledWith(
            "/proveedor/suscripciones/planes",
            expect.any(Object),
        );
    });

    it("corrige el plan que se abre desde su tarjeta", async () => {
        const pantalla = montar();

        await boton(pantalla, "Corregir").trigger("click");
        pulsar(botonDelModal("Guardar plan"));

        expect(enviarPut).toHaveBeenCalledWith(
            "/proveedor/suscripciones/planes/2",
            expect.any(Object),
        );
    });

    it("desactiva un plan sin borrarlo", async () => {
        const pantalla = montar();

        await boton(pantalla, "Desactivar").trigger("click");

        expect(post).toHaveBeenCalledWith(
            "/proveedor/suscripciones/planes/2/estado",
            {},
            expect.any(Object),
        );
    });

    it("no ofrece abrir suscripción si no hay planes que contratar", () => {
        const pantalla = montar({ planesParaElegir: [] });

        expect(boton(pantalla, "Abrir suscripción")).toBeUndefined();
    });

    it("abre la suscripción de una empresa sin ninguna", async () => {
        const pantalla = montar();

        await boton(pantalla, "Abrir suscripción").trigger("click");
        pulsar(botonDelModal("Abrir suscripción"));

        expect(enviarPost).toHaveBeenCalledWith(
            "/proveedor/suscripciones",
            expect.any(Object),
        );
    });
});
