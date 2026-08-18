import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const enviar = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    router: {
        post: (...argumentos) => enviar(...argumentos),
    },
}));

const EncabezadoApp = (await import("./EncabezadoApp.vue")).default;

const USUARIO = {
    nombre: "Angela Lozano Ríos",
    correo: "angela@progreso.co",
};

function montar(props = {}) {
    return mount(EncabezadoApp, {
        props: {
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            usuario: USUARIO,
            ...props,
        },
        attachTo: document.body,
    });
}

describe("EncabezadoApp", () => {
    beforeEach(() => {
        enviar.mockClear();
        document.body.innerHTML = "";
    });

    it("muestra la empresa donde se está trabajando, con su NIT", () => {
        const texto = montar().text();

        expect(texto).toContain("Distribuidora El Progreso S.A.S.");
        expect(texto).toContain("NIT 900123456-7");
    });

    it("arma el avatar con las iniciales del nombre y el apellido", () => {
        expect(montar().text()).toContain("AL");
    });

    it("no abre el menú de perfil hasta que se pulsa", async () => {
        const componente = montar();

        expect(componente.find('[role="menu"]').exists()).toBe(false);

        await componente.find("button[aria-haspopup]").trigger("click");

        expect(componente.find('[role="menu"]').text()).toContain(
            "angela@progreso.co",
        );
    });

    it("cierra la sesión con un POST, nunca con un enlace", async () => {
        const componente = montar();

        await componente.find("button[aria-haspopup]").trigger("click");
        await componente.find('[role="menuitem"]').trigger("click");

        expect(enviar).toHaveBeenCalledWith("/salir");
    });

    it("cierra el menú de perfil con la tecla de escape", async () => {
        const componente = montar();

        await componente.find("button[aria-haspopup]").trigger("click");
        expect(componente.find('[role="menu"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
        await componente.vm.$nextTick();

        expect(componente.find('[role="menu"]').exists()).toBe(false);
    });

    it("pide abrir el menú lateral desde el botón de pantalla pequeña", async () => {
        const componente = montar();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");

        expect(componente.emitted("abrir-menu")).toHaveLength(1);
    });
});
