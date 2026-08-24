import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const enviarFormulario = vi.fn();
const post = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => {
    const { reactive } = await import("vue");

    return {
        ...(await importarOriginal()),
        Head: { name: "Head", template: "<span />" },
        usePage: () => ({ url: "/proveedor/demostracion", props: { secciones: [] } }),
        useForm: (datos) =>
            reactive({
                ...datos,
                errors: {},
                processing: false,
                post: (...argumentos) => enviarFormulario(...argumentos),
                reset: vi.fn(),
            }),
        router: {
            post: (...argumentos) => post(...argumentos),
        },
    };
});

const Demostracion = (await import("./Demostracion.vue")).default;

const base = {
    restauradoEn: "23/08/2026, 3:00 a. m.",
    personas: 8,
    accesosActivos: [
        {
            id: 4,
            nombreProspecto: "Comercializadora Prospecto S.A.S.",
            generadoEn: "20/08/2026",
            expiraEn: "27/08/2026",
            diasDeVigencia: 7,
            ultimoUso: null,
        },
    ],
    enlaceGenerado: null,
};

function montar(cambios = {}) {
    return mount(Demostracion, {
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
    post.mockClear();
    document.body.innerHTML = "";
});

describe("la pantalla de demostración", () => {
    it("enseña cuándo quedó restaurada y cuántas personas trae", () => {
        const pantalla = montar();

        expect(pantalla.text()).toContain("23/08/2026, 3:00 a. m.");
        expect(pantalla.text()).toContain("8");
    });

    it("dice que nunca se ha restaurado cuando no hay fecha", () => {
        const pantalla = montar({ restauradoEn: null });

        expect(pantalla.text()).toContain("Todavía no se ha restaurado");
    });

    it("pide restaurar el ambiente al pulsar el botón", async () => {
        const pantalla = montar();

        await boton(pantalla, "Restaurar ahora").trigger("click");

        expect(post).toHaveBeenCalledWith(
            "/proveedor/demostracion/restaurar",
            {},
            expect.any(Object),
        );
    });

    it("genera un acceso con el nombre y los días digitados", async () => {
        const pantalla = montar();

        await pantalla
            .find('input[type="text"]')
            .setValue("Nuevo prospecto S.A.S.");
        await pantalla.find('input[type="number"]').setValue("10");
        await pantalla.find("form").trigger("submit");

        expect(enviarFormulario).toHaveBeenCalledWith(
            "/proveedor/demostracion/accesos",
            expect.any(Object),
        );
    });

    it("enseña el enlace recién generado para copiarlo", () => {
        const pantalla = montar({ enlaceGenerado: "http://nexo.test/prueba/abc123" });

        expect(pantalla.text()).toContain("http://nexo.test/prueba/abc123");
    });

    it("no enseña ningún enlace cuando no se acaba de generar ninguno", () => {
        const pantalla = montar();

        expect(pantalla.text()).not.toContain("Cópialo y envíaselo");
    });

    it("pide confirmar antes de revocar, con el nombre del prospecto", async () => {
        const pantalla = montar();

        await boton(pantalla, "Revocar").trigger("click");

        const dialogo = document.body.querySelector('[role="dialog"]');

        expect(dialogo.textContent).toContain("Comercializadora Prospecto S.A.S.");
        expect(post).not.toHaveBeenCalled();
    });

    it("revoca el acceso de la fila correspondiente al confirmar", async () => {
        const pantalla = montar();

        await boton(pantalla, "Revocar").trigger("click");
        pulsar(botonDelModal("Revocar el acceso"));
        await pantalla.vm.$nextTick();

        expect(post).toHaveBeenCalledWith(
            "/proveedor/demostracion/accesos/4/revocar",
            {},
            expect.any(Object),
        );
    });
});
