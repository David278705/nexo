import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/salud",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            { clave: "salud", etiqueta: "Salud del sistema", grupo: "Sistema", url: "/proveedor/salud" },
        ],
    },
};

const post = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
    usePage: () => pagina,
    router: {
        post: (...argumentos) => post(...argumentos),
    },
}));

const Salud = (await import("./Salud.vue")).default;

const base = {
    proceso: {
        hoy: { fecha: "24/08/2026", hora: "6:02 a. m.", duracion: "41 s", resultado: "23 alertas", exitosa: true },
        historial: [
            { fecha: "24/08/2026", hora: "6:02 a. m.", duracion: "41 s", resultado: "23 alertas", exitosa: true },
        ],
    },
    cola: { pendientes: 0, fallidos: [] },
    errores: { ultimas24h: 0, filas: [] },
    copias: {
        disponible: true,
        texto: "Railway hace copias automáticas con Point-in-Time Recovery (documento 6, A.5).",
    },
    espacioBd: "412 MB",
    correosDelMes: 1847,
};

function montar(props = {}) {
    return mount(Salud, { props: { ...base, ...props } });
}

function boton(pantalla, texto) {
    return pantalla.findAll("button").find((uno) => uno.text().includes(texto));
}

describe("Salud del sistema", () => {
    it("enseña la hora y el resultado del proceso de hoy cuando ya corrió", () => {
        const texto = montar().text();

        expect(texto).toContain("6:02 a. m.");
        expect(texto).toContain("Corrió bien");
    });

    it("avisa en rojo cuando el proceso todavía no ha corrido hoy", () => {
        const texto = montar({ proceso: { hoy: null, historial: [] } }).text();

        expect(texto).toContain("Sin ejecutar");
        expect(texto).toContain("Todavía no ha corrido hoy");
    });

    it("dice que las copias de seguridad son automáticas por Point-in-Time Recovery", () => {
        const texto = montar().text();

        expect(texto).toContain("A.5");
        expect(texto).toContain("Automática");
    });

    it("enseña el espacio de la base de datos y los correos del mes", () => {
        const texto = montar().text();

        expect(texto).toContain("412 MB");
        expect(texto).toContain("1847");
    });

    it("reintenta un trabajo fallido de la cola", async () => {
        const pantalla = montar({
            cola: {
                pendientes: 1,
                fallidos: [
                    {
                        uuid: "a1b2",
                        cola: "default",
                        excepcion: "Exception: el proveedor de correo no respondió",
                        falloEn: "2026-08-24 06:10:00",
                    },
                ],
            },
        });

        await boton(pantalla, "Reintentar").trigger("click");

        expect(post).toHaveBeenCalledWith(
            "/proveedor/salud/cola/a1b2/reintentar",
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
