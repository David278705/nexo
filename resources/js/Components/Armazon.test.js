import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/empleados?pagina=2",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                { clave: "inicio", etiqueta: "Inicio", grupo: null, url: "/" },
                {
                    clave: "empleados",
                    etiqueta: "Empleados",
                    grupo: "Personal",
                    url: "/empleados",
                },
            ],
        },
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    usePage: () => pagina,
    router: { post: vi.fn() },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Armazon = (await import("./Armazon.vue")).default;

function montar() {
    return mount(Armazon, {
        slots: { default: "<p>Contenido de la pantalla</p>" },
        attachTo: document.body,
    });
}

/*
 * Cuántas barras laterales hay pintadas. Una es la fija de escritorio; la
 * segunda solo aparece cuando el menú se despliega sobre el contenido.
 */
function barras(componente) {
    return componente.findAll('nav[aria-label="Secciones"]');
}

describe("Armazon", () => {
    it("envuelve el contenido de la pantalla", () => {
        expect(montar().text()).toContain("Contenido de la pantalla");
    });

    it("pinta el menú con los módulos compartidos y el encabezado con la empresa", () => {
        const texto = montar().text();

        expect(texto).toContain("Empleados");
        expect(texto).toContain("Distribuidora El Progreso S.A.S.");
    });

    it("resalta la sección aunque la dirección traiga parámetros", () => {
        const activos = montar().findAll('[aria-current="page"]');

        expect(activos).toHaveLength(1);
        expect(activos[0].attributes("href")).toBe("/empleados");
    });

    it("mantiene el menú replegado hasta que se pide abrirlo", async () => {
        const componente = montar();

        expect(barras(componente)).toHaveLength(1);

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");

        expect(barras(componente)).toHaveLength(2);
    });

    it("cierra el menú desplegado desde su botón de cerrar", async () => {
        const componente = montar();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");
        await componente.find('[aria-label="Cerrar el menú"]').trigger("click");

        expect(barras(componente)).toHaveLength(1);
    });

    it("cierra el menú desplegado al elegir una sección", async () => {
        const componente = montar();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");
        await barras(componente)[1].findAll("a")[1].trigger("click");

        expect(barras(componente)).toHaveLength(1);
    });

    it("cierra el menú desplegado con la tecla de escape", async () => {
        const componente = montar();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
        await componente.vm.$nextTick();

        expect(barras(componente)).toHaveLength(1);
    });

    /*
     * Los cuatro siguientes son el mismo mecanismo de foco que ya prueba
     * `Modal.test.js` para F6-06: este menú es el único diálogo del sistema
     * que se quedó sin él, porque no es un `Modal.vue`, es su propio armazón
     * de barra desplegada.
     */
    it("al desplegarse pone el foco dentro del menú", async () => {
        const componente = montar();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");
        await componente.vm.$nextTick();

        const dialogo = document.body.querySelector('[role="dialog"]');
        expect(dialogo.contains(document.activeElement)).toBe(true);

        componente.unmount();
    });

    it("atrapa el tabulador dentro del menú desplegado: del último vuelve al primero", async () => {
        const componente = montar();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");
        await componente.vm.$nextTick();

        document.body.querySelector('[aria-label="Cerrar el menú"]').focus();

        const evento = new KeyboardEvent("keydown", {
            key: "Tab",
            cancelable: true,
        });
        document.dispatchEvent(evento);

        expect(evento.defaultPrevented).toBe(true);
        expect(document.activeElement.getAttribute("href")).toBe("/");

        componente.unmount();
    });

    it("al cerrar el menú devuelve el foco a quien lo abrió", async () => {
        const componente = montar();

        const disparador = componente.find('[aria-label="Abrir el menú"]').element;
        disparador.focus();

        await componente.find('[aria-label="Abrir el menú"]').trigger("click");
        await componente.vm.$nextTick();
        await componente.find('[aria-label="Cerrar el menú"]').trigger("click");

        expect(document.activeElement).toBe(disparador);

        componente.unmount();
    });
});
